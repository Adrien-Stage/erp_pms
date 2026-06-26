<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class InvoiceController extends Controller
{
    public function show(Invoice $invoice)
    {
        $invoice->load([
            'booking.room.roomType',
            'customer',
            'items',
        ]);

        $tenant = $invoice->booking->tenant;

        return view('invoices.show', compact('invoice', 'tenant'));
    }
}
