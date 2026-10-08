<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionDocumentController extends Controller
{
    public function show(Request $request, Transaction $transaction, TransactionDocument $document): StreamedResponse
    {
        abort_unless($document->transaction_id === $transaction->id, 404);
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->response($document->path, $document->type->value.'.pdf');
    }
}
