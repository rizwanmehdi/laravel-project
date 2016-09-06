<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\PersonnelAlert;
use App\ClientChartAlert;

class PagesController extends Controller
{
    public function home(Request $request)
    {
        $personnel_alerts = PersonnelAlert::where('user_id', '=', $request->user()->id)
                                ->where('notify', '=', 'on')
                                ->get();

        $client_chart_alerts = ClientChartAlert::where('user_id', '=', $request->user()->id)
                                ->where('notify', '=', 'on')
                                ->get();

        return view('home', [
            'personnel_alerts' => $personnel_alerts,
            'client_chart_alerts' => $client_chart_alerts
        ]);
    }

    public function webmail(Request $request)
    {
        return view('pages.webmail', []);
    }

    public function calendar(Request $request, $email)
    {
        return view('pages.calendar', [
            'email' => $email
        ]);
    }

    public function oldAdmin(Request $request)
    {
        return view('pages.old_admin', []);
    }
}
