<?php

namespace App\Http\Controllers;

use App\Form;
use App\Document;
use App\Client;
use App\User;
use App\Ctimesheet;

use Carbon\Carbon;

use Auth;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use PhpParser\Comment\Doc;

class DocumentsController extends Controller
{
    private $_special_fields;

    public function __construct()
    {
        $this->_special_fields = [
            'start_date',
            'end_date',
            'duration',
            'units',
            'client_id',
            'document_type',
            'service_type',
            'goals',
            'counselor_id',
            'reviewer_id',
            'reviewer_2_id'
        ];

        $this->_carry_over_fields = [
            ['type' => 'textarea', 'id' => 'goals'],
        ];
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request, $form_id, $client_id)
    {
        $client = Client::find($client_id);
        $form = $this->_parseShortcodes(Form::find($form_id), $client);


        // try to figure out which was the last document, if any, belongs to the client
        $last_document = Document::where('client_id', '=', $client_id)
                                    ->where('document_type', '!=', 'pn')
                                    ->orderBy('created_at')->first();

        if ($form->document_type == 'pn') {
            if ($last_document) {
                $form = $this->_parseCarryOver($form, $last_document);
            }
        }

        return view('documents.form', [
            'form' => $form,
            'client' => $client,
            'parsed_data' => []
        ]);

    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function  store(Request $request, $form_id)
    {
        $form = Form::find($form_id);

        $data = file_get_contents('php://input');
        // if there are start and end dates, we need to figure out duration and units
        $data = $this->_clean_form_data__extract_special_fields__calculate_duration_and_units($data);

        $document = new Document();
        $document->user_id = Auth::user()->id;
        $document->form_id = $form_id;
        $document->html = $request->get('formhtml');
        $document->data = str_replace(["\r\n"], '\n', urldecode($data['data']));

        foreach ($this->_special_fields as $sf) {
            if (isset($data['data_raw'][$sf])) {
                $document->{$sf} = $data['data_raw'][$sf];
            }
        }

        $document->save();

        // creates the counselor timesheet
        $this->_updateOrNewCTimesheet($document);

        // creates the new client chart alert
        $this->_updateOrNewClientChartAlert($document);

        $request->session()->flash('info', 'New report created!');

        return '';
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $document = Document::find($id);
        $view = 1;
        $client = Client::find($document->client_id);
        parse_str($document->data, $parsed_data);
        $parsed_data = str_replace(["\r\n"], '\n', $parsed_data);
        if (isset($parsed_data['data'])) {
            unset($parsed_data['data']);
        }
        if (isset($parsed_data['_token'])) {
            unset($parsed_data['_token']);
        }
        $form = $this->_parseShortcodes(Form::find($document->form_id), $client);

        $form->html = $document->html;
        return view('documents.form', [
            'document' => $document,
            'form' => $form,
            'parsed_data' => $parsed_data,
            'client' => $client,
            'view' => $view
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $document = Document::find($id);
        $client = Client::find($document->client_id);
        parse_str($document->data, $parsed_data);
        $parsed_data = str_replace(["\r\n"], '\n', $parsed_data);
        if (isset($parsed_data['data'])) {
            unset($parsed_data['data']);
        }
        if (isset($parsed_data['_token'])) {
            unset($parsed_data['_token']);
        }
        $form = $this->_parseShortcodes(Form::find($document->form_id), $client);

        $form->html = $document->html;

        return view('documents.form', [
            'document' => $document,
            'form' => $form,
            'parsed_data' => $parsed_data,
            'client' => $client
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
        $data = file_get_contents('php://input');
        $data = $this->_clean_form_data__extract_special_fields__calculate_duration_and_units($data);

        $document = Document::find($id);
        $document->data = $data['data'];
        $document->html = $request->get("formhtml");
        foreach ($this->_special_fields as $sf) {
            if (isset($data['data_raw'][$sf])) {
                $document->{$sf} = $data['data_raw'][$sf];
            }
        }

        if ($request->user()->is('counselor')) {
            $document->counselor_updated_at = date('Y-m-d H:i:s');
        }

        $document->save();

        $this->_updateOrNewCTimesheet($document);

        $request->session()->flash('info', 'Report updated!');

        return '';
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, $client_id = null)
    {
        $document = Document::find($id);
        $form = Form::find($document->form_id);
        $ctimesheet = Ctimesheet::where('document_id', '=', $document->id);
        $document->delete();
        $ctimesheet->delete();
        if ($form->document_type == 'pn' || $form->document_type == 'no_contact_log' || $form->document_type == 'less_than_3_units') {
            return redirect('/clients/progress_notes/' . $client_id);
        }
        return redirect('/clients/reports/' . $client_id);

    }


    private function _parseShortcodes($form, $client)
    {
        // do client-select
        if (strstr($form->html, '[[[client-select]]]')) {
            $form->html = str_replace('[[[client-select]]]', $this->_buildClientSelector($client), $form->html);
        }

        // do counselor select
        if (strstr($form->html, '[[[counselor-select]]]')) {
            $form->html = str_replace('[[[counselor-select]]]', $this->_buildCounselorSelector($form->service_type), $form->html);
        }

        // do reviewer select
        if (strstr($form->html, '[[[reviewer-select]]]')) {
            $form->html = str_replace('[[[reviewer-select]]]', $this->_buildReviewerSelector($form->service_type), $form->html);
        }

        // do reviewer2 select
        if (strstr($form->html, '[[[reviewer-2-select]]]')) {
            $form->html = str_replace('[[[reviewer-2-select]]]', $this->_buildReviewer2Selector($form->service_type), $form->html);
        }

        // do supervisee select
        if (strstr($form->html, '[[[supervisee-select]]]')) {
            $form->html = str_replace('[[[supervisee-select]]]', $this->_buildReviewerSelector($form->service_type), $form->html);
        }

        // do client's name replace
        if (strstr($form->html, '[[[clients-name]]]')) {
            $form->html = str_replace('[[[clients-name]]]', $client->name, $form->html);
        }

        if (strstr($form->html, '[[[local-emergency-services-number]]]')) {
            $form->html = str_replace('[[[local-emergency-services-number]]]', '***TODO: GET EMERGENCY NUMBERS!***', $form->html);
        }

        return $form;
    }

    private function _buildClientSelector($client)
    {
        // todo: refactor the name of this function since it's not a selector anymore
        $output = '<input type="text" class="form-control" value="' . $client->name . '" disabled>';
        $output .= '<input type="hidden" name="client_id" id="client_id" value="' . $client->id . '">';

        return $output;
    }

    private function _buildCounselorSelector($service_type)
    {
        if (Auth::user()->hasRole('counselor')) {
            $users = User::where('id', '=', Auth::user()->id)->get();
        } else {
            $users = User::orderBy('name')->get();
        }

        $output = '<select class="form-control" name="counselor_id" id="counselor_id">';
        $output .= '<option value="">Select...</option>';
        foreach ($users as $user) {
//            if ($user->hasRole('counselor')) {
                $signature_file = null;
                $signature_files = json_decode($user->signature_files);
                if (!empty($signature_files)) {
                    if ($service_type == 'iih') {
                        if (isset($signature_files->iih)) {
                            $signature_file = $signature_files->iih;
                        }
                    } elseif ($service_type == 'mhss') {
                        if (isset($signature_files->mhss)) {
                            $signature_file = $signature_files->mhss;
                        }
                    }
                }

//                if (isset($signature_file)) {
                    $output .= '<option data-src="' . $signature_file . '" value="' . $user->id . '">' . $user->name . '</option>';
//                }

//            }
        }
        $output .= '</select>';

        $output .= "
        <script>
        $(document).ready(function() {
            $('#counselor_id').change(function() {
                var a = $('option:selected', this).attr('data-src');
                if (a.length > 0) {
                    $('#signature_file').attr('src', '/uploads/signatures/' + $('option:selected', this).attr('data-src'));
                }
            });

        });
        </script>
        ";

        $output .= '<div id="signature_file_wrapper"><img src="" id="signature_file"/></div>';
        return $output;
    }

    private function _buildReviewerSelector($service_type)
    {
        $output = '';
        if (!Auth::user()->is('counselor')) {
            $users = User::orderBy('name')->get();
            $output .= '<select class="form-control" name="reviewer_id" id="reviewer_id">';
            $output .= '<option value="">Select...</option>';
            foreach ($users as $user) {
//            if ($user->hasRole('reviewer')) {
                $signature_file = null;
                $signature_files = json_decode($user->signature_files);
                if (!empty($signature_files)) {

                    if (isset($signature_files->iih)) {
                        $signature_file = $signature_files->iih;
                    }

                    if (isset($signature_files->mhss)) {
                        $signature_file = $signature_files->mhss;
                    }

                    if (isset($signature_files->reg)) {
                        $signature_file = $signature_files->reg;
                    }

//                    if (isset($signature_file)) {
                    $output .= '<option data-src="' . $signature_file . '" value="' . $user->id . '">' . $user->name . '</option>';
//                    }
                }


//            }
            }
            $output .= '</select>';

            $output .= "
        <script>
        $(document).ready(function() {
            $('#reviewer_id').change(function() {
                var a = $('option:selected', this).attr('data-src');
                if (a.length > 0) {
                    $('#reviewer_signature_file').attr('src', '/uploads/signatures/' + $('option:selected', this).attr('data-src'));
                }
            });

        });
        </script>
        ";

            $output .= '<div id="reviewer_signature_file_wrapper"><img src="" id="reviewer_signature_file"/></div>';
        } else {
            $output .= '[ reviewers signature b not available for this user role]';
        }


        return $output;
    }

    private function _buildReviewer2Selector($service_type)
    {
        $output = '';
        if (!Auth::user()->is('counselor')) {
            $users = User::orderBy('name')->get();
            $output .= '<select class="form-control" name="reviewer_id" id="reviewer_id">';
            $output .= '<option value="">Select...</option>';
            foreach ($users as $user) {
//            if ($user->hasRole('reviewer')) {
                $signature_file = null;
                $signature_files = json_decode($user->signature_files);
                if (!empty($signature_files)) {

                    if (isset($signature_files->iih)) {
                        $signature_file = $signature_files->iih;
                    }

                    if (isset($signature_files->mhss)) {
                        $signature_file = $signature_files->mhss;
                    }

                    if (isset($signature_files->reg)) {
                        $signature_file = $signature_files->reg;
                    }

//                    if (isset($signature_file)) {
                    $output .= '<option data-src="' . $signature_file . '" value="' . $user->id . '">' . $user->name . '</option>';
//                    }
                }


//            }
            }
            $output .= '</select>';

            $output .= "
        <script>
        $(document).ready(function() {
            $('#reviewer_id').change(function() {
                var a = $('option:selected', this).attr('data-src');
                if (a.length > 0) {
                    $('#reviewer_signature_file').attr('src', '/uploads/signatures/' + $('option:selected', this).attr('data-src'));
                }
            });

        });
        </script>
        ";

            $output .= '<div id="reviewer_signature_file_wrapper"><img src="" id="reviewer_signature_file"/></div>';
        } else {
            $output .= '[ reviewers signature a not available for this user role]';
        }


        return $output;
    }

    /**
     * @param String a URL query string
     * @return array array with data, start_date, end_date, duration, and units
     */
    private function _clean_form_data__extract_special_fields__calculate_duration_and_units($data)
    {
        // parse the data into an array
        parse_str($data, $data);

        if (isset($data['formhtml'])) {
            unset($data['formhtml']);
        }

        // remove the _token field because we don't really need it now
        if (isset($data['_token'])) {
            unset($data['_token']);
        }

        // remove the _method field
        if (isset($data['_method'])) {
            unset($data['_method']);
        }

        // remove all empty items
        foreach ($data as $key => $value) {
            if (empty($data[$key])) {
                unset($data[$key]);
            }
        }


        // find the special fields
        $data_with_special_fields = $this->_parse_special_fields($data);
        $data_with_special_fields = $this->_calculate_duration_and_units($data_with_special_fields);


        $data['data'] = $data;

        // make the data back into a string
        $data = http_build_query($data['data']);

        $return = [
            'data' => $data,
            'data_raw' => $data_with_special_fields
        ];

        return $return;
    }

    private function _calculate_duration_and_units($data)
    {
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $sd = Carbon::createFromTimestamp(strtotime($data['start_date']));
            $ed = Carbon::createFromTimestamp(strtotime($data['end_date']));
            $duration = $sd->diffInMinutes($ed);
            $data['duration'] = $duration;
            $data['units'] = $this->_calculate_units_from_duration($duration, $data['document_type'], $data['service_type']);
        } else {
            $duration = 0;
            $data['duration'] = $duration;
            $data['units'] = $this->_calculate_units_from_duration($duration, $data['document_type'], $data['service_type']);
        }


        return $data;
    }

    private function _calculate_units_from_duration($duration, $doc_type, $serv_type)
    {
        /**
         * If IIH
         *  1 hour - 1:59 hours = 1 Unit
         *  2 hours - 2:59 hours = 2 Units
         *  3+ hours = 3 Units
         *
         * If MHSS
         *  1 hour - 2:59 hours = 1 Unit
         *  3 hours - 4:59 hours = 2 Units
         *  5 hours - 6:59 hours = 3 Units
         *  7+ hours = 4 Units
         *
         * If Assessment (IIH and MHSS) are 1 Unit no matter the duration
         */

        if ($doc_type == 'assessment') {
            return 1; // always return 1 if assessment
        } else {
            if ($serv_type == 'IIH') {
                if ($duration >= 180) {
                    return 3;
                } elseif ($duration >= 120) {
                    return 2;
                } elseif ($duration >= 60) {
                    return 1;
                } else {
                    return 0;
                }
            } elseif ($serv_type = 'MHSS') {
                if ($duration >= 420) {
                    return 4;
                } elseif ($duration >= 300) {
                    return 3;
                } elseif ($duration >= 180) {
                    return 2;
                } elseif ($duration >= 60) {
                    return 1;
                } else {
                    return 0;
                }
            } else {
                return 0;
            }
        }
        return 0;
    }

    private function _parse_special_fields($data)
    {
        foreach ($data as $key => $value) {
            foreach ($this->_special_fields as $sf) {
                if ($key == $sf) {
                    $data[$key] = $value;
                }
            }
        }
        return $data;
    }

    /**
     * This function wants things "just so" or it won't work. The following strings must match:
     * <textarea ... ... id="yourstring" ></textarea>
     * <input type="text" ... ... id="yourstring" value="" />
     */
    private function _parseCarryOver($form, $last_document)
    {
        foreach ($this->_carry_over_fields as $type => $id)
        {
            if ($type == 'textarea') {
                //<textarea.+id="goals".[^>]+>.[^<]+</textarea>
                preg_match('/<textarea.+id=\"goals\"*.[^>]+>(.[^<]+)?<\/textarea>/', $last_document->html, $matches_last_document);
                preg_match('/<textarea.+id=\"goals\"*.[^>]+><\/textarea>/', $form->html, $matches_form);
                if (isset($matches_last_document[0])) { // we found the textarea
                    if (isset($matches_form[0])) { // the original form had it too
//                        // replace it
//                        $form->html = str_replace($matches_form[0], $matches_last_document[0], $form->html);
//
//                        // if it's summernot
//                        if (strstr($matches_last_document, 'summernote')) {
//
//                        }
                        $form->html = $form->html . '
                            <script>
                            $(document).ready(function() {
                            alert("here");
                            $("#goals").code("' . str_replace("\r\n", "", $last_document->goals) . '");
                            });
                            </script>

                        ';
                    }
                }
            }
        }
        return $form;
    }


    private function _updateOrNewCTimesheet($document)
    {

        if (isset($document->counselor_id) && isset($document->duration)) {
            $ctimesheet = Ctimesheet::where('document_id', '=', $document->id)->first();
            if (!$ctimesheet) {
                $ctimesheet = new Ctimesheet();
            }
            $ctimesheet->counselor_id = $document->counselor_id;
            $ctimesheet->document_id = $document->id;
            if ($document->reviewer_id) {
                $ctimesheet->reviewer_id = $document->reviewer_id;
                $ctimesheet->status = 'reviewed';
            }
            $ctimesheet->client_id = $document->client_id;
            $ctimesheet->start_date = $document->start_date;
            $ctimesheet->end_date = $document->end_date;
            $ctimesheet->duration = $document->duration;
            $ctimesheet->units = $document->units;
            $ctimesheet->save();
        }
    }

    private function _updateOrNewClientChartAlert($document)
    {

    }
}
