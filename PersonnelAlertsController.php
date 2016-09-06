<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;

use App\User;
use App\PersonnelAlert;
use App\PersonnelAlertTitle;

class PersonnelAlertsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = User::orderBy('name')->get();

        return view('personnel_alerts.index', [
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
        $personnel_alert = new PersonnelAlert();
        $personnel_alert_titles = PersonnelAlertTitle::all();
        $users = User::orderBy('name')->get();

        return view('personnel_alerts.form', [
            'personnel_alert' => $personnel_alert,
            'personnel_alert_titles' => $personnel_alert_titles,
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
            $personnel_alert = new PersonnelAlert();
            $personnel_alert->user_id = $user_id;
            $personnel_alert->created_by = $request->user()->id;
            $personnel_alert->personnel_alert_title_id = $request->get('personnel_alert_title_id');
            $personnel_alert->expiration_date = $request->get('expiration_date');
            $personnel_alert->first_notify_num_days_before = $request->get('first_notify_num_days_before');
            $personnel_alert->second_notify_num_days_before = $request->get('second_notify_num_days_before');
            $personnel_alert->notify = $request->get('notify');
            $personnel_alert->save();

        }

        return redirect('/alerts/personnel_alerts');
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
        $personnel_alert = PersonnelAlert::find($id);
        $personnel_alert_titles = PersonnelAlertTitle::all();

        return view('personnel_alerts.form', [
            'personnel_alert' => $personnel_alert,
            'personnel_alert_titles' => $personnel_alert_titles
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
        $personnel_alert = PersonnelAlert::find($id);
        $personnel_alert->user_id = $request->get('user_id');
        $personnel_alert->created_by = $request->user()->id;
        $personnel_alert->personnel_alert_title_id = $request->get('personnel_alert_title_id');
        $personnel_alert->expiration_date = $request->get('expiration_date');
        $personnel_alert->first_notify_num_days_before = $request->get('first_notify_num_days_before');
        $personnel_alert->second_notify_num_days_before = $request->get('second_notify_num_days_before');
        $personnel_alert->notify = $request->get('notify');
        $personnel_alert->save();

        return redirect('/alerts/personnel_alerts');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $personnel_alert = PersonnelAlert::find($id);
        $personnel_alert->delete();

        return redirect('/alerts/personnel_alerts');
    }
}
