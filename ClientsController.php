<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\Document;
use App\Client;
use App\Form;
use App\Resource;

class ClientsController extends Controller
{



    public function reports(Request $request, $client_id)
    {
        $title = "Assessments/Reports";
        $client = Client::find($client_id);

        $documents = Document::where('client_id', '=', $client_id)
                            ->where('document_type', '!=', 'pn')
                            ->orderBy('start_date', 'desc')
                            ->get();

        $forms = Form::where('document_type', '!=', 'pn')
                        ->where('service_type', '=', strtoupper($client->type))
                        ->get();

        return view('clients.reports', [
            'documents' => $documents,
            'forms' => $forms,
            'client' => $client,
            'title' => $title
        ]);
    }

    public function progressNotes(Request $request, $client_id)
    {
        $title = "Progress Notes";
        $client = Client::find($client_id);

        $documents = Document::where('client_id', '=', $client_id)
                            ->where(function ($query) {
                                $query->where('document_type', '=', 'pn')
                                    ->orWhere('document_type', '=', 'no_contact_log')
                                    ->orWhere('document_type', '=', 'less_than_3_units');
                            })
                            ->orderBy('start_date', 'desc')
                            ->get();

        $forms = Form::where(function ($query) {
                            $query->where('document_type', '=', 'pn')
                                ->orWhere('document_type', '=', 'no_contact_log')
                                ->orWhere('document_type', '=', 'less_than_3_units');
                        })
                            ->where('service_type', '=', strtoupper($client->type))
                            ->get();

        return view('clients.reports', [
            'documents' => $documents,
            'forms' => $forms,
            'client' => $client,
            'title' => $title
        ]);
    }

    public function correspondence(Request $request, $client_id)
    {
        $correspondence = Resource::where('client_id', '=', $client_id)->get();
        $client = Client::find($client_id);
        if ($request->user()->is('admin|compliance.coordinator|clinical.supervisor')) {
            $page_heading = 'Correspondence : ' . $client->name . ' <a href="/resources/create/' . $client->id . '" class="btn btn-primary">Add</a>';
        } else {
            $page_heading = 'Correspondence : ' . $client->name;
        }


        return view('clients.correspondence.index' , [
            'correspondence' => $correspondence,
            'client' => $client,
            'page_heading' => $page_heading

        ]);
    }
}
