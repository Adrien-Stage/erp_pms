{{--
    Formulaire d'edition du contenu du site vitrine d'un etablissement.

    Attend : $tenant, $actionUrl (destination du POST)

    Extrait pour etre partage entre la fiche etablissement de l'ERP et
    l'espace dedie a l'editeur de contenu : un champ ajoute ici apparait
    des deux cotes, au lieu de diverger.
--}}
                @php
                    $schemaPages = \App\Support\SiteContentSchema::pages();
                    $pagesData   = \App\Support\SiteContentSchema::hydrate($tenant->site_content);
                    $seo         = $tenant->site_content['seo'] ?? [];
                @endphp
                <div class="mb-6">
                    <h2 class="text-xl font-extrabold text-slate-800 tracking-tight">Contenu du site</h2>
                    <p class="text-xs text-slate-500 mt-1">Construis le contenu de chaque page du site vitrine de {{ $tenant->name }} — un onglet par page, des champs regroupés par section. Chaque section peut être affichée ou masquée sur le site via son interrupteur. Les chambres et le menu restaurant viennent directement de l'application.</p>
                </div>

                <form action="{{ $actionUrl }}" method="POST" enctype="multipart/form-data" x-data="{ tab: 'identity' }">
                    @csrf

                    {{-- Barre d'onglets (identité + une entrée par page du site + SEO global) --}}
                    <div class="bg-white rounded-t-xl border border-b-0 border-slate-200 px-3 pt-3 flex flex-wrap gap-1">
                        <button type="button" @click="tab = 'identity'"
                                class="px-4 py-2.5 rounded-t-lg text-xs font-bold transition border-b-2 cursor-pointer"
                                :class="tab === 'identity' ? 'text-indigo-700 border-indigo-600 bg-indigo-50/60' : 'text-slate-500 border-transparent hover:text-slate-800 hover:bg-slate-50'">
                            Identité du site
                        </button>
                        @foreach($schemaPages as $pageKey => $pageDef)
                            <button type="button" @click="tab = '{{ $pageKey }}'"
                                    class="px-4 py-2.5 rounded-t-lg text-xs font-bold transition border-b-2 cursor-pointer"
                                    :class="tab === '{{ $pageKey }}' ? 'text-indigo-700 border-indigo-600 bg-indigo-50/60' : 'text-slate-500 border-transparent hover:text-slate-800 hover:bg-slate-50'">
                                {{ $pageDef['label'] }}
                            </button>
                        @endforeach
                        <button type="button" @click="tab = 'seo'"
                                class="px-4 py-2.5 rounded-t-lg text-xs font-bold transition border-b-2 cursor-pointer"
                                :class="tab === 'seo' ? 'text-indigo-700 border-indigo-600 bg-indigo-50/60' : 'text-slate-500 border-transparent hover:text-slate-800 hover:bg-slate-50'">
                            SEO
                        </button>
                    </div>

                    <div class="bg-slate-50/60 border border-slate-200 rounded-b-xl p-5">

                        {{-- Onglet Identité : nom, logo et coordonnées affichés sur le site.
                             Édite directement la fiche du tenant (mêmes champs que le
                             formulaire de création / l'onglet Informations) — une seule
                             source de vérité, pas de doublon dans site_content. --}}
                        <div x-show="tab === 'identity'" class="space-y-5">
                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Identité du site</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">Nom, logo et coordonnées affichés dans l'en-tête, le pied de page et la section contact du site. Ces informations sont celles de l'établissement (renseignées à sa création) — les modifier ici les met aussi à jour dans l'onglet Informations. Sans logo, le site affiche la première lettre du nom.</p>
                                </div>
                                <div class="p-6 space-y-5">
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Nom de l'établissement</label>
                                        <input type="text" value="{{ $tenant->name }}" disabled
                                               class="block w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm text-slate-500 cursor-not-allowed">
                                        <p class="text-[10px] text-slate-400 mt-1">Le nom se modifie depuis l'onglet Informations.</p>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Logo</label>
                                        <div class="flex items-center gap-4">
                                            @if(!empty($tenant->settings['logo']))
                                                <div class="relative shrink-0">
                                                    <img src="{{ asset('storage/' . $tenant->settings['logo']) }}" alt="Logo" class="h-16 w-24 object-contain rounded-lg border border-slate-200 bg-slate-950 p-1">
                                                    <label class="absolute -top-2 -right-2 flex items-center gap-1 bg-white border border-slate-200 rounded-full px-1.5 py-0.5 shadow-sm cursor-pointer" title="Supprimer le logo à l'enregistrement">
                                                        <input type="checkbox" name="identity_remove_logo" value="1" class="h-3 w-3 rounded text-red-600">
                                                        <span class="text-[9px] font-bold text-red-500">Suppr.</span>
                                                    </label>
                                                </div>
                                            @else
                                                <div class="h-16 w-16 shrink-0 rounded-lg bg-indigo-600 text-white flex items-center justify-center font-extrabold text-2xl">
                                                    {{ strtoupper(mb_substr($tenant->name, 0, 1)) }}
                                                </div>
                                            @endif
                                            <input type="file" name="identity_logo" accept="image/*"
                                                   class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                                        </div>
                                        @if(empty($tenant->settings['logo']))
                                            <p class="text-[10px] text-slate-400 mt-1.5">Aucun logo — le site affiche actuellement la première lettre du nom (aperçu ci-dessus).</p>
                                        @endif
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Téléphone</label>
                                            <input type="text" name="identity_phone" value="{{ old('identity_phone', $tenant->phone) }}"
                                                   class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Email</label>
                                            <input type="email" name="identity_email" value="{{ old('identity_email', $tenant->email) }}"
                                                   class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Adresse</label>
                                        <input type="text" name="identity_address" value="{{ old('identity_address', $tenant->address) }}"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Onglets pages : sections générées depuis le schéma --}}
                        @foreach($schemaPages as $pageKey => $pageDef)
                            <div x-show="tab === '{{ $pageKey }}'" x-cloak class="space-y-5">
                                @foreach($pageDef['sections'] as $sectionKey => $sectionDef)
                                    @php $sd = $pagesData[$pageKey][$sectionKey]; @endphp
                                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ on: {{ $sd['enabled'] ? 'true' : 'false' }} }">

                                        {{-- En-tête de section : libellé + interrupteur d'affichage --}}
                                        <div class="px-6 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
                                            <div>
                                                <h3 class="text-sm font-bold transition" :class="on ? 'text-slate-800' : 'text-slate-400'">{{ $sectionDef['label'] }}</h3>
                                                <p class="text-[10px] text-slate-400 mt-0.5 leading-relaxed">{{ $sectionDef['description'] }}</p>
                                            </div>
                                            <label class="shrink-0 flex items-center gap-2 mt-0.5 cursor-pointer select-none">
                                                <span class="text-[10px] font-bold uppercase tracking-wider transition" :class="on ? 'text-indigo-600' : 'text-slate-400'" x-text="on ? 'Affichée' : 'Masquée'"></span>
                                                <input type="checkbox" name="pages[{{ $pageKey }}][{{ $sectionKey }}][enabled]" value="1" x-model="on" hidden>
                                                <div class="h-5 w-9 rounded-full transition-colors" :class="on ? 'bg-indigo-600' : 'bg-slate-200'">
                                                    <div class="h-4 w-4 mt-0.5 rounded-full bg-white shadow transition-transform" :class="on ? 'translate-x-[18px]' : 'translate-x-0.5'"></div>
                                                </div>
                                            </label>
                                        </div>

                                        {{-- Champs de la section (repliés quand elle est masquée) --}}
                                        <div class="p-6 space-y-4" x-show="on">
                                            @foreach($sectionDef['fields'] as $fieldKey => $fieldDef)
                                                @php
                                                    $inputName = "pages[{$pageKey}][{$sectionKey}][{$fieldKey}]";
                                                    $fileName  = "pages_files[{$pageKey}][{$sectionKey}][{$fieldKey}]";
                                                    $value     = $sd[$fieldKey];
                                                @endphp

                                                @if($fieldDef['type'] === 'text')
                                                    <div>
                                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">{{ $fieldDef['label'] }}</label>
                                                        <input type="text" name="{{ $inputName }}" value="{{ old($inputName, $value) }}"
                                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                                    </div>

                                                @elseif($fieldDef['type'] === 'textarea')
                                                    <div>
                                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">{{ $fieldDef['label'] }}</label>
                                                        <textarea name="{{ $inputName }}" rows="3"
                                                                  class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">{{ old($inputName, $value) }}</textarea>
                                                    </div>

                                                @elseif($fieldDef['type'] === 'items')
                                                    <div>
                                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">{{ $fieldDef['label'] }}</label>
                                                        <textarea name="{{ $inputName }}" rows="4" placeholder="{{ $fieldDef['placeholder'] ?? '' }}"
                                                                  class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 font-mono outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">{{ old($inputName, \App\Support\SiteContentSchema::itemsToRaw($value, $fieldDef['keys'])) }}</textarea>
                                                        <p class="text-[10px] text-slate-400 mt-1">Un élément par ligne — colonnes séparées par « | » ({{ implode(' | ', array_map(fn ($k) => ucfirst($k), $fieldDef['keys'])) }}).</p>
                                                    </div>

                                                @elseif($fieldDef['type'] === 'image')
                                                    <div>
                                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">{{ $fieldDef['label'] }}</label>
                                                        <div class="flex items-center gap-4">
                                                            @if($value)
                                                                <div class="relative shrink-0">
                                                                    <img src="{{ asset('storage/' . $value) }}" alt="" class="h-16 w-24 object-cover rounded-lg border border-slate-200">
                                                                    <label class="absolute -top-2 -right-2 flex items-center gap-1 bg-white border border-slate-200 rounded-full px-1.5 py-0.5 shadow-sm cursor-pointer" title="Supprimer cette image à l'enregistrement">
                                                                        <input type="checkbox" name="pages[{{ $pageKey }}][{{ $sectionKey }}][remove_{{ $fieldKey }}]" value="1" class="h-3 w-3 rounded text-red-600">
                                                                        <span class="text-[9px] font-bold text-red-500">Suppr.</span>
                                                                    </label>
                                                                </div>
                                                            @endif
                                                            <input type="file" name="{{ $fileName }}" accept="image/*"
                                                                   class="text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                                                        </div>
                                                    </div>

                                                @elseif($fieldDef['type'] === 'images')
                                                    <div>
                                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">{{ $fieldDef['label'] }}</label>
                                                        @if(count($value))
                                                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
                                                                @foreach($value as $path)
                                                                    <label class="relative block rounded-lg overflow-hidden border border-slate-200 cursor-pointer group">
                                                                        <img src="{{ asset('storage/' . $path) }}" alt="" class="h-24 w-full object-cover">
                                                                        <div class="absolute inset-0 bg-black/0 group-has-[:checked]:bg-red-900/60 transition flex items-center justify-center">
                                                                            <span class="hidden group-has-[:checked]:block text-white text-[10px] font-bold">Supprimer</span>
                                                                        </div>
                                                                        <input type="checkbox" name="pages[{{ $pageKey }}][{{ $sectionKey }}][remove_{{ $fieldKey }}][]" value="{{ $path }}" class="absolute top-1.5 right-1.5 h-4 w-4 rounded">
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                            <p class="text-[10px] text-slate-400 mb-2">Coche une photo pour la supprimer lors de l'enregistrement.</p>
                                                        @endif
                                                        <input type="file" name="{{ $fileName }}[]" accept="image/*" multiple
                                                               class="block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-indigo-700">
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>

                                        {{-- Rappel discret quand la section est masquée --}}
                                        <div class="px-6 py-3 text-[10px] text-slate-400 italic" x-show="!on" x-cloak>
                                            Section masquée sur le site — son contenu est conservé et sera réaffiché si tu la réactives.
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach

                        {{-- Onglet SEO global --}}
                        <div x-show="tab === 'seo'" x-cloak class="space-y-5">
                            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                                <div class="px-6 py-4 border-b border-slate-100">
                                    <h3 class="text-sm font-bold text-slate-800">Référencement (SEO)</h3>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Titre et description affichés dans les résultats de recherche.</p>
                                </div>
                                <div class="p-6 space-y-4">
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Titre de la page</label>
                                        <input type="text" name="seo_title" value="{{ old('seo_title', $seo['title'] ?? '') }}" placeholder="{{ $tenant->name }}"
                                               class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] font-bold tracking-wider text-slate-400 uppercase mb-1.5">Meta description</label>
                                        <textarea name="seo_description" rows="2"
                                                  class="block w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-700 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20">{{ old('seo_description', $seo['description'] ?? '') }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Enregistrement global (toutes pages confondues) --}}
                        <div class="flex items-center justify-between gap-4 mt-6">
                            <p class="text-[10px] text-slate-400">L'enregistrement sauvegarde toutes les pages et sections d'un coup, y compris celles des autres onglets.</p>
                            <button type="submit" class="shrink-0 rounded-lg bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white hover:bg-indigo-700 transition shadow-sm">
                                Enregistrer le contenu
                            </button>
                        </div>

                    </div>
                </form>
