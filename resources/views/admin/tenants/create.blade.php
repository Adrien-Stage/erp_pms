@php
    $tabs = [
        'dashboard' => ['label' => 'Supervision'],
        'tenants' => ['label' => 'Etablissements'],
        'managers' => ['label' => 'Managers'],
        'roles' => ['label' => 'Roles'],
        'modules' => ['label' => 'Modules'],
        'audit' => ['label' => 'Audit'],
        'support' => ['label' => 'Support'],
        'settings' => ['label' => 'Configuration'],
        'billing' => ['label' => 'Licences'],
        'imports' => ['label' => 'Import/Export'],
        'system' => ['label' => 'Systeme'],
    ];
    $activeTab = 'tenants';

    // Suggest next app port dynamically
    $maxPort = \App\Models\Tenant::max('app_port') ?? 8080;
    $nextPort = $maxPort + 1;
@endphp

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un Nouvel Établissement - Administration</title>
    <meta name="description" content="Création d'un nouvel établissement en mode multi-étapes.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased font-body">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-30 w-full bg-[#0f172a] border-b border-slate-800 text-white shadow-md">
        <div class="mx-auto max-w-7xl px-5 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center gap-8">
                <div class="text-sm font-extrabold uppercase tracking-wider text-white">
                    MEKA ERP
                </div>
                <nav class="hidden md:flex items-center gap-1.5" aria-label="Navigation administration">
                    @foreach($tabs as $key => $tab)
                        <a
                            href="{{ route('tech.dashboard', ['tab' => $key]) }}"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold tracking-wide transition {{ $activeTab === $key ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}"
                        >
                            {{ $tab['label'] }}
                        </a>
                    @endforeach
                </nav>
            </div>
            
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-md border border-slate-700 bg-slate-800 px-3.5 py-1.5 text-xs font-bold text-slate-200 transition hover:bg-slate-700 hover:text-white">
                    Déconnexion
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto min-h-screen w-full max-w-4xl px-5 py-8 lg:px-8" x-data="{
        step: 1,
        ownerType: 'new', // 'new' or 'existing'
        
        // Step 1: Owner Info (New)
        ownerName: '',
        ownerEmail: '',
        ownerPhone: '',
        ownerCompany: '',
        ownerPassword: 'owner',
        ownerNationality: 'Camerounaise',
        
        // Step 1: Owner Info (Existing)
        existingOwnerId: '',

        // Step 2: Technical Instance Info
        dbName: '',
        dbUsername: 'pms',
        dbPassword: 'secret',
        appPort: '{{ $nextPort }}',
        dbPort: '5432',
        dockerAppContainer: '',
        dockerDbContainer: '',

        // Step 3: Establishment Info
        name: '',
        slug: '',
        autoSlug: true,
        country: 'Cameroun',
        currency: 'XAF',
        city: 'Douala',
        address: '',
        phone: '',
        email: '',
        logoPreview: null,
        themePrimary: '#391F0E',
        themeSecondary: '#CCAB87',
        themeAccent: '#EED4A3',
        themeDark: '#0F0201',
        themeSurfaceDark: '#2C1810',
        themeTextOnLight: '#391F0E',
        themeTextOnDark: '#CCAB87',
        
        // Modules Selection
        modules: {
            hotel: true,
            restaurant: false,
            shop: false,
            housekeeping: false,
            accounting: true,
            ai: false,
            api: true,
            website: true
        },

        // Methods
        applyPalette(p, s, a, d, sd, tl, td) {
            this.themePrimary = p;
            this.themeSecondary = s;
            this.themeAccent = a;
            this.themeDark = d;
            this.themeSurfaceDark = sd;
            this.themeTextOnLight = tl;
            this.themeTextOnDark = td;
        },
        handleLogoChange(e) {
            const file = e.target.files[0];
            if (file) {
                this.logoPreview = URL.createObjectURL(file);
            }
        },
        generateSlug() {
            if (this.autoSlug) {
                this.slug = this.name
                    .toLowerCase()
                    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                
                // Prefill database name and containers based on the establishment slug
                this.dbName = this.slug.replace(/-/g, '_') + '_db';
                this.dockerAppContainer = 'meka-app-' + this.slug;
                this.dockerDbContainer = 'meka-db-' + this.slug;
            }
        },
        prefillInstanceFromOwner() {
            // If the user entered a company name in Step 1, we can use it to generate defaults if Name is empty
            if (this.ownerCompany && !this.name) {
                this.name = this.ownerCompany;
                this.generateSlug();
            }
        },
        toggleModuleDependency(moduleName) {
            // Site Web requires API
            if (moduleName === 'website' && this.modules.website) {
                this.modules.api = true;
            }
            // If API is disabled, Site Web must be disabled
            if (moduleName === 'api' && !this.modules.api) {
                this.modules.website = false;
            }
        },
        nextStep() {
            if (this.step === 1) {
                if (this.ownerType === 'new') {
                    if (!this.ownerName || !this.ownerEmail || !this.ownerCompany) {
                        alert('Veuillez remplir les informations obligatoires du propriétaire (Nom, Email, Entreprise).');
                        return;
                    }
                } else {
                    if (!this.existingOwnerId) {
                        alert('Veuillez sélectionner un propriétaire existant.');
                        return;
                    }
                }
                // Prefill step 2 & 3 values based on Owner inputs
                this.prefillInstanceFromOwner();
            } else if (this.step === 2) {
                if (!this.dbName || !this.appPort) {
                    alert('Veuillez remplir les informations obligatoires de l\'instance (Nom de base de données, Port de l\'application).');
                    return;
                }
            }
            this.step++;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        prevStep() {
            this.step--;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }">

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs text-slate-400 mb-6">
            <a href="{{ route('tech.dashboard', ['tab' => 'tenants']) }}" class="hover:text-indigo-600 transition font-semibold">Établissements</a>
            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            <span class="text-slate-600 font-bold">Nouvel Établissement</span>
        </nav>

        <!-- Stepper Progress Bar -->
        <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm mb-8">
            <div class="relative flex items-center justify-between">
                <!-- Line Background -->
                <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 h-0.5 bg-slate-100 -z-0"></div>
                <!-- Dynamic active bar -->
                <div class="absolute left-0 top-1/2 -translate-y-1/2 h-0.5 bg-indigo-600 transition-all duration-300 -z-0"
                    :style="step === 1 ? 'width: 0%;' : (step === 2 ? 'width: 50%;' : 'width: 100%;')">
                </div>

                <!-- Step 1 Indicator -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs transition duration-300"
                        :class="step === 1 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-green-500 text-white'">
                        <span x-show="step === 1">1</span>
                        <svg x-show="step > 1" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold tracking-wide mt-2 uppercase transition"
                        :class="step === 1 ? 'text-indigo-600' : 'text-slate-500'">Propriétaire</span>
                </div>

                <!-- Step 2 Indicator -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs transition duration-300"
                        :class="step === 2 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : (step > 2 ? 'bg-green-500 text-white' : 'bg-white border-2 border-slate-200 text-slate-400')">
                        <span x-show="step <= 2">2</span>
                        <svg x-show="step > 2" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <span class="text-[10px] font-bold tracking-wide mt-2 uppercase transition"
                        :class="step === 2 ? 'text-indigo-600' : 'text-slate-400'">Configuration</span>
                </div>

                <!-- Step 3 Indicator -->
                <div class="relative z-10 flex flex-col items-center">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-xs transition duration-300"
                        :class="step === 3 ? 'bg-indigo-600 text-white ring-4 ring-indigo-100' : 'bg-white border-2 border-slate-200 text-slate-400'">
                        <span>3</span>
                    </div>
                    <span class="text-[10px] font-bold tracking-wide mt-2 uppercase transition"
                        :class="step === 3 ? 'text-indigo-600' : 'text-slate-400'">Établissement & Modules</span>
                </div>
            </div>
        </div>

        <!-- Page Header -->
        <div class="border-b border-slate-200 pb-5 mb-8">
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 font-heading">
                <span x-show="step === 1">Étape 1 : Rattachement du Propriétaire</span>
                <span x-show="step === 2">Étape 2 : Configuration de l'Instance</span>
                <span x-show="step === 3">Étape 3 : Informations & Modules</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1.5">
                <span x-show="step === 1">Renseignez le compte du propriétaire de l'établissement (création d'un nouveau compte ou sélection d'un compte existant).</span>
                <span x-show="step === 2">Configurez la base de données PostgreSQL isolée et les ports/conteneurs Docker associés à cette instance.</span>
                <span x-show="step === 3">Saisissez les détails de l'établissement (Nom, Slug, Devise, Logo) et activez ses modules métiers.</span>
            </p>
        </div>

        <!-- Validation Errors (Laravel backend) -->
        @if($errors->any())
            <div class="mb-6 rounded-lg bg-red-50 border border-red-200 p-4 shadow-sm">
                <div class="flex items-start gap-3">
                    <svg class="h-5 w-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    <div>
                        <h3 class="text-xs font-bold text-red-800">Erreurs de validation</h3>
                        <ul class="mt-1.5 list-disc list-inside text-xs text-red-700 space-y-0.5">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif

        <form action="{{ route('tech.establishments.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
            @csrf

            <!-- ================= STEP 1: OWNER (PROPRIÉTAIRE) ================= -->
            <div x-show="step === 1" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-6">
                <!-- Tab Selector: Create or Select -->
                <div class="bg-white rounded-xl border border-slate-200 p-2 shadow-sm flex gap-2">
                    <button type="button" @click="ownerType = 'new'"
                        class="flex-1 rounded-lg py-2.5 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
                        :class="ownerType === 'new' ? 'bg-[#0f172a] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        Nouveau Propriétaire
                    </button>
                    <button type="button" @click="ownerType = 'existing'"
                        class="flex-1 rounded-lg py-2.5 text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
                        :class="ownerType === 'existing' ? 'bg-[#0f172a] text-white shadow-sm' : 'text-slate-500 hover:bg-slate-50'">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        Sélectionner un existant
                    </button>
                </div>

                <input type="hidden" name="owner_type" :value="ownerType">

                <!-- Option A: New Owner Form -->
                <div x-show="ownerType === 'new'" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-600/20 p-2">
                            <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Créer le Compte Propriétaire</h2>
                            <p class="text-[10px] text-slate-400">Ces identifiants lui permettront d'accéder à l'Espace Business</p>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Owner Name -->
                            <div>
                                <label for="owner_name" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom complet <span class="text-red-400">*</span></label>
                                <input type="text" id="owner_name" name="owner_name" x-model="ownerName" placeholder="Ex: Jean-Pierre Meka"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                            <!-- Owner Email -->
                            <div>
                                <label for="owner_email" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse e-mail <span class="text-red-400">*</span></label>
                                <input type="email" id="owner_email" name="owner_email" x-model="ownerEmail" placeholder="jp.meka@mekagroup.com"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <!-- Owner Company Name -->
                            <div class="sm:col-span-2">
                                <label for="owner_company" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom de l'entreprise / Groupe <span class="text-red-400">*</span></label>
                                <input type="text" id="owner_company" name="owner_company" x-model="ownerCompany" placeholder="Ex: Meka Resort Group"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <p class="text-[9px] text-slate-400 mt-1">Sera utilisé pour suggérer des valeurs de configuration de l'instance.</p>
                            </div>
                            <!-- Owner Nationality -->
                            <div>
                                <label for="owner_nationality" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nationalité <span class="text-red-400">*</span></label>
                                <input type="text" id="owner_nationality" name="owner_nationality" x-model="ownerNationality" placeholder="Ex: Camerounaise"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Owner Phone -->
                            <div>
                                <label for="owner_phone" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                                <input type="text" id="owner_phone" name="owner_phone" x-model="ownerPhone" placeholder="+237 699 123 456"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                            <!-- Owner Password -->
                            <div>
                                <div class="flex justify-between items-center">
                                    <label for="owner_password" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Mot de passe temporaire <span class="text-red-400">*</span></label>
                                </div>
                                <input type="text" id="owner_password" name="owner_password" x-model="ownerPassword"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <p class="text-[9px] text-slate-400 mt-1">Par défaut : <code class="font-bold">owner</code>. L'utilisateur pourra le modifier ultérieurement.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Option B: Select Existing Owner -->
                <div x-show="ownerType === 'existing'" class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-600/20 p-2">
                            <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Sélectionner un Propriétaire</h2>
                            <p class="text-[10px] text-slate-400">Rattacher le nouvel établissement à un propriétaire existant</p>
                        </div>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label for="owner_id" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Sélectionner le compte propriétaire <span class="text-red-400">*</span></label>
                            <select id="owner_id" name="owner_id" x-model="existingOwnerId"
                                    class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <option value="">Choisir un propriétaire dans la liste...</option>
                                @foreach($owners as $owner)
                                    <option value="{{ $owner->id }}" {{ old('owner_id') == $owner->id ? 'selected' : '' }}>
                                        {{ $owner->name }} ({{ $owner->email }}) {{ $owner->company_name ? '— ' . $owner->company_name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Section Actions -->
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('tech.dashboard', ['tab' => 'tenants']) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm">
                        Annuler
                    </a>
                    <button type="button" @click="nextStep()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                        <span>Suivant</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ================= STEP 2: CONFIGURATION TECHNIQUE (INSTANCE) ================= -->
            <div x-show="step === 2" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-6">
                
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-violet-600/20 p-2">
                            <svg class="h-5 w-5 text-violet-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0v3.75" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Base de Données PostgreSQL & Ports</h2>
                            <p class="text-[10px] text-slate-400">Paramétrage d'isolation de l'infrastructure locale</p>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-5">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Database Name -->
                            <div>
                                <label for="db_name" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom de la base de données <span class="text-red-400">*</span></label>
                                <input type="text" id="db_name" name="db_name" x-model="dbName" placeholder="Ex: meka_resort_db" required
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <p class="text-[9px] text-slate-400 mt-1">Sera créée de façon isolée sur le serveur PostgreSQL.</p>
                            </div>
                            <!-- App Port -->
                            <div>
                                <label for="app_port" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Port d'écoute local <span class="text-red-400">*</span></label>
                                <input type="number" id="app_port" name="app_port" x-model="appPort" required
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <p class="text-[9px] text-slate-400 mt-1">Port local unique alloué à l'application web. Port suivant suggéré.</p>
                            </div>
                        </div>

                        <!-- DB Credentials Box -->
                        <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-5 space-y-4">
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wide border-b border-slate-100 pb-2">Identifiants d'accès PostgreSQL</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                                <div>
                                    <label for="db_username" class="block text-[9px] font-bold text-slate-400 uppercase">Utilisateur</label>
                                    <input type="text" id="db_username" name="db_username" x-model="dbUsername"
                                           class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label for="db_password" class="block text-[9px] font-bold text-slate-400 uppercase">Mot de passe</label>
                                    <input type="password" id="db_password" name="db_password" x-model="dbPassword"
                                           class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500">
                                </div>
                                <div>
                                    <label for="db_port" class="block text-[9px] font-bold text-slate-400 uppercase">Port DB</label>
                                    <input type="number" id="db_port" name="db_port" x-model="dbPort"
                                           class="mt-1 block w-full rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-blue-600/20 p-2">
                            <svg class="h-5 w-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3V7.5a3 3 0 013-3h13.5a3 3 0 013 3v3.75a3 3 0 01-3 3zm-13.5 0a3 3 0 00-3 3v3.75a3 3 0 003 3h13.5a3 3 0 003-3V18a3 3 0 00-3-3m-13.5 0h13.5" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Infrastructure Docker (Optionnel)</h2>
                            <p class="text-[10px] text-slate-400">Noms des conteneurs associés à cette instance localement</p>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="docker_app_container" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Conteneur Applicatif</label>
                                <input type="text" id="docker_app_container" name="docker_app_container" x-model="dockerAppContainer" placeholder="Ex: meka-app-resort"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                            <div>
                                <label for="docker_db_container" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Conteneur de Données</label>
                                <input type="text" id="docker_db_container" name="docker_db_container" x-model="dockerDbContainer" placeholder="Ex: meka-db-resort"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Actions -->
                <div class="flex items-center justify-between pt-2">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Précédent
                    </button>
                    <button type="button" @click="nextStep()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm shadow-indigo-200 cursor-pointer">
                        <span>Suivant</span>
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ================= STEP 3: ESTABLISHMENT INFO & MODULES ================= -->
            <div x-show="step === 3" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" class="space-y-6">
                
                <!-- Section: Identité de l'établissement -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-indigo-600/20 p-2">
                            <svg class="h-5 w-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.053.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Fiche de l'Établissement</h2>
                            <p class="text-[10px] text-slate-400">Nom public, code d'accès et coordonnées physiques</p>
                        </div>
                    </div>
                    
                    <div class="p-6 space-y-5">
                        <!-- Logo Upload -->
                        <div>
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-2">Logo de l'établissement</label>
                            <div class="flex items-center gap-5">
                                <div class="h-20 w-28 bg-slate-950 border-2 border-dashed border-slate-700 rounded-xl flex items-center justify-center overflow-hidden transition-colors hover:border-indigo-500">
                                    <template x-if="logoPreview">
                                        <img :src="logoPreview" alt="Logo preview" class="h-16 object-contain">
                                    </template>
                                    <template x-if="!logoPreview">
                                        <div class="text-center">
                                            <svg class="h-6 w-6 text-slate-600 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                            </svg>
                                            <span class="text-[8px] text-slate-500 font-bold mt-1 block">PNG, JPG</span>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <input type="file" name="logo" accept="image/*" @change="handleLogoChange($event)" class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                                    <p class="text-[10px] text-slate-400 mt-1">Format PNG transparent recommandé. Max 2 Mo.</p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Nom de l'établissement <span class="text-red-400">*</span></label>
                                <input type="text" id="name" name="name" x-model="name" @input="generateSlug()" required placeholder="Ex: Meka Resort Douala"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                            </div>
                            <!-- Slug -->
                            <div>
                                <label for="slug" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Slug / Sous-domaine <span class="text-red-400">*</span></label>
                                <input type="text" id="slug" name="slug" x-model="slug" @input="autoSlug = false" required placeholder="ex: meka-resort-douala"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition">
                                <p class="text-[9px] text-slate-400 mt-1">Sert d'accès en local : <code class="font-bold font-mono">http://[slug].localhost:8080</code></p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                            <!-- Country -->
                            <div>
                                <label for="country" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Pays</label>
                                <input type="text" id="country" name="country" x-model="country"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 transition">
                            </div>
                            <!-- City -->
                            <div>
                                <label for="city" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Ville</label>
                                <input type="text" id="city" name="city" x-model="city"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 transition">
                            </div>
                            <!-- Currency -->
                            <div>
                                <label for="currency" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Devise <span class="text-red-400">*</span></label>
                                <input type="text" id="currency" name="currency" x-model="currency" required maxlength="3"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono uppercase outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>

                        <!-- Address -->
                        <div>
                            <label for="address" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Adresse physique</label>
                            <input type="text" id="address" name="address" x-model="address" placeholder="Ex: Boulevard de la liberté, Akwa"
                                   class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 transition">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <!-- Phone -->
                            <div>
                                <label for="phone" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">Téléphone</label>
                                <input type="text" id="phone" name="phone" x-model="phone" placeholder="+237 233 456 789"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 transition">
                            </div>
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase">E-mail de contact</label>
                                <input type="email" id="email" name="email" x-model="email" placeholder="contact@mekaresort.com"
                                       class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 transition">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ======= SECTION 3: Thème & Couleurs ======= -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-[#a21caf]/20 p-2">
                            <svg class="h-5 w-5 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.098 19.902a3.75 3.75 0 005.304 0l6.401-6.402M6.75 21A3.75 3.75 0 013 17.25V4.125C3 3.504 3.504 3 4.125 3h5.25c.621 0 1.125.504 1.125 1.125v4.072M6.75 21a3.75 3.75 0 003.75-3.75V8.197M6.75 21h13.125c.621 0 1.125-.504 1.125-1.125v-5.25c0-.621-.504-1.125-1.125-1.125h-4.072M10.5 8.197l2.88-2.88c.438-.439 1.15-.439 1.59 0l3.712 3.713c.44.44.44 1.152 0 1.59l-2.879 2.88M6.75 17.25h.008v.008H6.75v-.008z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Thème & Couleurs de la Filiale</h2>
                            <p class="text-[10px] text-slate-400">Choisissez une palette ou personnalisez les couleurs de l'interface</p>
                        </div>
                    </div>
                    <div class="p-6 space-y-5">
                        <!-- Preset Palettes -->
                        <div>
                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-3">Palettes prédéfinies</label>
                            <div class="flex items-center gap-3 flex-wrap">
                                <!-- Terracotta -->
                                <button type="button"
                                    @click="applyPalette('#391F0E', '#CCAB87', '#EED4A3', '#0F0201', '#2C1810', '#391F0E', '#CCAB87')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #391F0E 50%, #CCAB87 50%);"
                                    title="Terracotta (Original)">
                                    <span x-show="themePrimary === '#391F0E'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                                <!-- Royal Blue -->
                                <button type="button"
                                    @click="applyPalette('#1E3A8A', '#3B82F6', '#93C5FD', '#0F172A', '#1E293B', '#FFFFFF', '#93C5FD')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #1E3A8A 50%, #3B82F6 50%);"
                                    title="Bleu Royal">
                                    <span x-show="themePrimary === '#1E3A8A'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                                <!-- Forest Green -->
                                <button type="button"
                                    @click="applyPalette('#064E3B', '#10B981', '#A7F3D0', '#022C22', '#064E3B', '#FFFFFF', '#A7F3D0')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #064E3B 50%, #10B981 50%);"
                                    title="Vert Forêt">
                                    <span x-show="themePrimary === '#064E3B'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                                <!-- Imperial Purple -->
                                <button type="button"
                                    @click="applyPalette('#4C1D95', '#8B5CF6', '#DDD6FE', '#1E1B4B', '#312E81', '#FFFFFF', '#DDD6FE')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #4C1D95 50%, #8B5CF6 50%);"
                                    title="Violet Impérial">
                                    <span x-show="themePrimary === '#4C1D95'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                                <!-- Rose -->
                                <button type="button"
                                    @click="applyPalette('#831843', '#EC4899', '#FCE7F3', '#500724', '#831843', '#FFFFFF', '#FCE7F3')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #831843 50%, #EC4899 50%);"
                                    title="Rose Vibrant">
                                    <span x-show="themePrimary === '#831843'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                                <!-- Sunset Orange -->
                                <button type="button"
                                    @click="applyPalette('#7C2D12', '#F97316', '#FFEDD5', '#431407', '#7C2D12', '#FFFFFF', '#FFEDD5')"
                                    class="w-8 h-8 rounded-full border-2 border-slate-200 hover:border-indigo-400 relative focus:outline-none cursor-pointer transition-all hover:scale-110 shadow-sm animate-none"
                                    style="background: linear-gradient(135deg, #7C2D12 50%, #F97316 50%);"
                                    title="Orange Couchant">
                                    <span x-show="themePrimary === '#7C2D12'" class="absolute inset-0 flex items-center justify-center text-white text-xs font-bold">✓</span>
                                </button>
                            </div>
                        </div>

                        <!-- Live Preview Card -->
                        <div class="rounded-xl border border-slate-200 overflow-hidden">
                            <div class="px-4 py-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase bg-slate-50 border-b border-slate-200">Aperçu en temps réel</div>
                            <div class="p-4 flex items-center gap-4">
                                <div class="rounded-lg overflow-hidden shadow-md border border-slate-200 w-48 shrink-0">
                                    <div class="h-10 flex items-center justify-center transition-colors duration-200" :style="'background-color:' + themePrimary">
                                        <span class="text-[9px] font-bold tracking-widest uppercase transition-colors duration-200" :style="'color:' + themeTextOnDark" x-text="name || 'NOM ÉTABLISSEMENT'"></span>
                                    </div>
                                    <div class="h-6 flex items-center justify-center transition-colors duration-200" :style="'background-color:' + themeSecondary">
                                        <span class="text-[8px] font-semibold transition-colors duration-200" :style="'color:' + themeTextOnLight">Menu Navigation</span>
                                    </div>
                                    <div class="h-10 bg-white flex items-center justify-center">
                                        <span class="text-[8px] text-slate-400">Contenu principal</span>
                                    </div>
                                </div>
                                <div class="text-xs text-slate-500 leading-relaxed">
                                    <p class="font-semibold text-slate-700 mb-1">Aperçu du thème</p>
                                    <p class="text-[10px]">Les couleurs sélectionnées seront appliquées à l'interface de l'établissement.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Color Pickers Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Primaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themePrimary" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[primary]" x-model="themePrimary" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Couleur Secondaire</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeSecondary" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[secondary]" x-model="themeSecondary" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Accent</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeAccent" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[accent]" x-model="themeAccent" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeDark" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[dark]" x-model="themeDark" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Surface Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeSurfaceDark" class="h-7 w-7 rounded cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[surface_dark]" x-model="themeSurfaceDark" class="block w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-[11px] text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Clair</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeTextOnLight" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_light]" x-model="themeTextOnLight" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 mb-1.5 font-semibold">Texte sur Fond Sombre</label>
                                <div class="flex items-center gap-2">
                                    <input type="color" x-model="themeTextOnDark" class="h-8 w-8 rounded-lg cursor-pointer border border-slate-200 shrink-0">
                                    <input type="text" name="theme[text_on_dark]" x-model="themeTextOnDark" class="block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 uppercase">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section: Sélection des Modules (Premium cards) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="bg-slate-900 px-6 py-4 flex items-center gap-3">
                        <div class="rounded-lg bg-emerald-600/20 p-2">
                            <svg class="h-5 w-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-white tracking-wide">Modules Actifs de la Filiale</h2>
                            <p class="text-[10px] text-slate-400">Sélectionnez les fonctionnalités métiers à déployer sur cette instance</p>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            
                            <!-- Module: Hôtel -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.hotel ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[hotel]" x-model="modules.hotel" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">Module Hôtel</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase">Core</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Gestion des chambres, tarifs saisonniers, réservations de séjours, et facturation d'hébergement.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: Restaurant -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.restaurant ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[restaurant]" x-model="modules.restaurant" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <span class="text-xs font-bold text-slate-800">Module Restaurant</span>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Gestion des menus, tables, prise de commande en salle, envoi en cuisine, et encaissement POS.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: Boutique -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.shop ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[shop]" x-model="modules.shop" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <span class="text-xs font-bold text-slate-800">Module Boutique / Point de Vente</span>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Vente de produits locaux ou souvenirs, suivi des stocks de marchandises et ventes directes.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: Housekeeping -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.housekeeping ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[housekeeping]" x-model="modules.housekeeping" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <span class="text-xs font-bold text-slate-800">Module Housekeeping</span>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Planification du nettoyage des chambres, attribution des tâches aux valets et statut de propreté.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: Comptabilité -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.accounting ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[accounting]" x-model="modules.accounting" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">Module Comptabilité</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 uppercase">Core</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Journal des dépenses, encaissements consolidés, écritures comptables et rapports de trésorerie.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: IA Assistante -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.ai ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[ai]" x-model="modules.ai" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <span class="text-xs font-bold text-slate-800">Module Intelligence Artificielle (Mistral)</span>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Analyse prédictive de remplissage, suggestions de tarifs optimisés et résumés financiers automatisés.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: API -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.api ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[api]" x-model="modules.api" @change="toggleModuleDependency('api')" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">API d'Intégration</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 uppercase">Dev</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Expose des routes API sécurisées pour connecter des applications mobiles tierces ou des PMS externes.
                                    </p>
                                </div>
                            </label>

                            <!-- Module: Site Web -->
                            <label class="relative flex items-start gap-4 rounded-xl border p-4 cursor-pointer select-none transition hover:bg-slate-50 duration-200"
                                :class="modules.website ? 'border-indigo-600 ring-2 ring-indigo-50 bg-indigo-50/10' : 'border-slate-200'">
                                <input type="checkbox" name="modules[website]" x-model="modules.website" @change="toggleModuleDependency('website')" class="mt-1 h-4 w-4 rounded text-indigo-600 focus:ring-indigo-500 shrink-0">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800">Site Web Vitrine (SvelteKit)</span>
                                        <span class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 uppercase border border-blue-100">Front</span>
                                    </div>
                                    <p class="text-[10px] text-slate-500 leading-relaxed">
                                        Génère un site vitrine public auto-géré pour l'établissement. *Nécessite l'API d'Intégration active.*
                                    </p>
                                </div>
                            </label>

                        </div>
                    </div>
                </div>

                <!-- Section Actions -->
                <div class="flex items-center justify-between pt-2 pb-12">
                    <button type="button" @click="prevStep()" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition shadow-sm cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                        </svg>
                        Précédent
                    </button>
                    
                    <!-- Submit Form -->
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-6 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm shadow-indigo-200 cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Créer l'Établissement
                    </button>
                </div>
            </div>

        </form>
    </main>
</body>
</html>
