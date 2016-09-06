<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\User;
use App\ClientChartAlert;
use App\ClientChartAlertTitle;

class ClientChartAlertsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::orderBy('name')->get();

        return view('client_chart_alerts.index', [
            'users' => $users
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $client_chart_alert = new ClientChartAlert();
        $client_chart_alert_titles = ClientChartAlertTitle::all();
        $users = User::orderBy('name')->get();


        return view('client_chart_alerts.form', [
            'client_chart_alert' => $client_chart_alert,
            'client_chart_alert_titles' => $client_chart_alert_titles,
            'users' => $users
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        foreach ($request->get('user_ids') as $user_id) {
            $client_chart_alert = new ClientChartAlert();
            $client_chart_alert->user_id = $user_id;
            $client_chart_alert->client_id = $request->get('client_id');
            $client_chart_alert->created_by = $request->user()->id;
            $client_chart_alert->client_chart_alert_title_id = $request->get('client_chart_alert_title_id');
            $client_chart_alert->due_date = $request->get('start_date');
            $client_chart_alert->start_date = $request->get('start_date');
            $client_chart_alert->end_date = $request->get('end_date');
            $client_chart_alert->name = $request->get('name');
            $client_chart_alert->first_notify_num_days_before = $request->get('first_notify_num_days_before');
            $client_chart_alert->second_notify_num_days_before = $request->get('second_notify_num_days_before');
            $client_chart_alert->notify = $request->get('notify');
            $client_chart_alert->save();

        }

        return redirect('/alerts/client_chart_alerts');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $client_chart_alert = ClientChartAlert::find($id);
        $client_chart_alert_titles = ClientChartAlertTitle::all();

        return view('client_chart_alerts.form', [
            'client_chart_alert' => $client_chart_alert,
            'client_chart_alert_titles' => $client_chart_alert_titles
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $client_chart_alert = ClientChartAlert::find($id);
        $client_chart_alert->user_id = $request->get('user_id');
        $client_chart_alert->client_id = $request->get('client_id');
        $client_chart_alert->created_by = $request->user()->id;
        $client_chart_alert->client_chart_alert_title_id = $request->get('client_chart_alert_title_id');
        $client_chart_alert->due_date = $request->get('start_date');
        $client_chart_alert->start_date = $request->get('start_date');
        $client_chart_alert->end_date = $request->get('end_date');
        $client_chart_alert->name = $request->get('name');
        $client_chart_alert->first_notify_num_days_before = $request->get('first_notify_num_days_before');
        $client_chart_alert->second_notify_num_days_before = $request->get('second_notify_num_days_before');
        $client_chart_alert->notify = $request->get('notify');
        $client_chart_alert->save();

        return redirect('/alerts/client_chart_alerts');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $client_chart_alert = ClientChartAlert::find($id);
        $client_chart_alert->delete();

        return redirect('/alerts/client_chart_alerts');
    }
}
