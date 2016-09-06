<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\ClientChartAlertTitle;

class ClientChartAlertTitlesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $client_chart_alert_titles = ClientChartAlertTitle::all();

        return view('client_chart_alerts.titles.index', [
            'client_chart_alert_titles' => $client_chart_alert_titles
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $client_chart_alert_title = new ClientChartAlertTitle();

        return view('client_chart_alerts.titles.form', [
            'client_chart_alert_title' => $client_chart_alert_title
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
        $client_chart_alert_title = new ClientChartAlertTitle();
        $client_chart_alert_title->title = $request->get('title');
        $client_chart_alert_title->save();

        return redirect('/alerts/client_chart_alerts/titles/');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $client_chart_alert_title = ClientChartAlertTitle::find($id);

        return view('client_chart_alerts.titles.form', [
            'client_chart_alert_title' => $client_chart_alert_title
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
        $client_chart_alert_title = ClientChartAlertTitle::find($id);
        $client_chart_alert_title->title = $request->get('title');
        $client_chart_alert_title->save();

        return redirect('/alerts/client_chart_alerts/titles/');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $client_chart_alert_title = ClientChartAlertTitle::find($id);
        $client_chart_alert_title->delete();

        return redirect('/alerts/client_chart_alerts/titles');
    }
}
