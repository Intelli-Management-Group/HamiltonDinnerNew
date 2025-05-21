<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF-Friendly Table</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        .container {
            width: 100%; 
            text-align: center; 
            margin-bottom: 20px;
        }
        .customsContainer {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .customsContainer div {
            display: table-cell;
            padding: 5px;
            vertical-align: middle; /* Align checkboxes and labels in the middle */
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            vertical-align: top; 
            padding: 10px; 
            text-align: left; 
        }
        td {
            vertical-align: top; 
            padding: 8px;
            text-align: left;
        }
        th, td {
            border: 1px solid gray;
            padding: 5px;
            /* text-align: left; */
            font-size: 12px;
            width: 25%; 
        }
        p, span {
            font-weight: 300;
            font-size: 12px;
        }
        tr {
            page-break-inside: avoid;
        }
        td, th {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .checkbox-label {
            display: flex;
            align-items: center; 
            margin: 10px 0; 
        }

        .checkbox-label input[type="checkbox"] {
            width: 20px; 
            height: 20px;
            margin-right: 5px; /* Space between checkbox and label */
            vertical-align: middle; /* Ensures vertical alignment */
        }

        .checkbox-group {
            display: flex;
            align-items: center; /* Align items vertically in the center */
            margin-left: 10px; 
        }      
        
    
        /* .checkbox-label {
            display: flex;
            justify-content: flex-start; 
            align-items: center; 
            margin: 10px 0; 
        }
        .checkbox-group {
            display: flex;
            justify-content: flex-start; 
            margin-left: 10px; 
        }           */
        h1 {
            color: #2E74B5; 
            font-family: "Times New Roman", serif; 
            font-style: normal; 
            font-weight: bold; 
            text-decoration: none; 
            font-size: 14pt; 
        }
        @media print {
            body {
                margin: 0;
                padding: 0;
                width: 100%;
            }
            .customsContainer {
                display: table;
                width: 100%;
            }

            .customsContainer div {
                display: table-cell;
                padding: 5px;
                vertical-align: middle; /* Ensure middle alignment when printed */
            }
            table {
                width: 100%;
            }
            tr, th, td {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .checkbox-container {
                display: flex;
                align-items: center;
            }
            input[type="checkbox"] {
                width: 20px; 
                height: 20px;
                margin: 0 5px 0 0;
                vertical-align: bottom;
                padding-top: 10pt; 
                
            }
           

            label {
                vertical-align: middle; 
                font-size: 14px;
            }

        }
    </style>
    
</head>
<body>

    <div class="customsContainer">
        <div>
            <span>INCIDENT INVOLVED:</span>
        </div>
        <div class="checkbox-label">
            <input type="checkbox" name="admin" value="yes" {{ $inc_invl_resident ? 'checked' : '' }}>
            <label for="resident">Resident</label>
        </div>
        <div class="checkbox-label">
            <input type="checkbox" name="admin" value="yes" {{ $inc_invl_visitor ? 'checked' : '' }}>
            <label for="visitor">Visitor</label>
        </div>
        <div class="checkbox-label">
            <input type="checkbox" name="admin" value="yes" {{ $inc_invl_staff ? 'checked' : '' }}>
            <label for="staff">Staff</label>
        </div>
        <div class="checkbox-label">
            <input type="checkbox" name="admin" value="yes" {{ $inc_invl_other ? 'checked' : '' }}>
            <label for="other">Other <u>{{ !empty($inc_invl_other_text) ? $inc_invl_other_text : "" }}</u></label>
        </div>
    </div>


    <div class="container">
        <table>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE OF INCIDENT (D/M/Y)</p>
                    </div>
                    <div style="text-align: center">{{ $incident_dt }}</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Time</p>
                    </div>
                    <div style="text-align: center">{{ $incident_tm }}</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">LOCATION OF INCIDENT</p>
                    </div>
                    {{$incident_location}}
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">WITNESSED BY</p>
                    </div>
                    {{$witnessed_by}}
                </th>
            </tr>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE OF DISCOVERY (D/M/Y)</p>
                    </div>
                    <div style="text-align: center">{{ $discovery_dt }}</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Time</p>
                    </div>
                    <div style="text-align: center">{{ $discovery_tm }}</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">LOCATION OF DISCOVERY</p>
                    </div>
                    {{ $discovery_location }}
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DISCOVERED BY</p>
                        {{$discovered_by}}
                    </div>
                </th>
            </tr>
            <tr>
                <th colspan="2">
                    <p>TYPE OF INCIDENT</p>
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="border: none; text-align: left;">
                                <div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_fall ? 'checked' : '' }}>
                                        <label for="fall">Fall</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_fire ? 'checked' : '' }}>
                                        <label for="fire">Fire</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_security ? 'checked' : '' }}>
                                        <label for="security">Security</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_elopement ? 'checked' : '' }}>
                                        <label for="elopement">Elopement</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_aggresiveBeh ? 'checked' : '' }}>
                                        <label for="aggressive">Aggressive Behavior</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_other ? 'checked' : '' }}>
                                        <label for="other-type">Other <u>{{ !empty($type_of_inc_other_text) ? $type_of_inc_other_text : "" }}</u></label>
                                    </div>
                                </div>

                            </td>
                            <td style="border: none; text-align: left;">
                                <div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_resAbase ? 'checked' : '' }}>
                                        <label for="resident-abuse">Resident Abuse</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_treatment ? 'checked' : '' }}>
                                        <label for="treatment">Treatment</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_lossOfProp ? 'checked' : '' }}>
                                        <label for="loss-property">Loss of Property</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_choking ? 'checked' : '' }}>
                                        <label for="choking">Choking</label>
                                    </div>
                                    <div class="checkbox-label">
                                        <input type="checkbox" name="admin" value="yes" {{ $type_of_inc_death ? 'checked' : '' }}>
                                        <label for="death">Death</label>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </th>
                <th>
                    <p>SAFETY DEVICES IN USE BEFORE OCCURRENCE</p>
                    <div style="text-align: center;">
                        Yes No N/A
                    </div>
                    <div style="width: 100%;">
                        <div class="checkbox-label">
                            <span>Fob was within reach</span>
                            <div class="checkbox-group">
                                <input type="checkbox" name="admin" value="yes" {{ $safety_fob == "Yes" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_fob == "No" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_fob == "N/A" ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Call bell within reach</span>
                            <div class="checkbox-group">
                                <input type="checkbox" name="admin" value="yes" {{ $safety_callbell == "Yes" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_callbell == "No" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_callbell == "N/A" ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Caution signs in place</span>
                            <div class="checkbox-group">
                                <input type="checkbox" name="admin" value="yes" {{ $safety_caution == "Yes" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_caution == "No" ? 'checked' : '' }}>
                                <input type="checkbox" name="admin" value="yes" {{ $safety_caution == "N/A" ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Other <u>{{ !empty($safety_other) ?  $safety_other : "" }}</u></span>
                        </div>
                    </div>
                </th>

                <th>
                    <p style="margin-top: 0px;">OTHER WITNESSES?</p>

                    <div style="width: 100%;">
                        <div class="checkbox-label" style="margin-left: 30px;">
                            <div class="checkbox-group">
                                <input type="checkbox" name="admin" value="yes" {{ $other_witnesses == "Yes" ? 'checked' : '' }}> 
                                <label for="yes" style="margin-right:10px">Yes</label>
                                <input type="checkbox" name="admin" value="yes" {{ $other_witnesses == "No" ? 'checked' : '' }}> 
                                <label for="no">No</label>
                            </div>
                        </div>
                    </div>

                    <div style="width: 100%;">
                        <div><p>Name:<u>{{ !empty($witness_name1) ?  $witness_name1 : "" }}</u></p></div>
                        <div><p>Position:<u>{{ !empty($witness_position1) ? $witness_position1 : "" }}</u></p></div>
                        <div><p>Name:<u>{{ !empty($witness_name2) ? $witness_name2 : "" }}</u></p></div>
                        <div><p>Position:<u>{{!empty($witness_position2) ? $witness_position2 : "" }}</u></p></div>
                    </div>
                </th>
            </tr>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Condition At Time Of Incident</p>
                        <div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $condition_at_inc_oriented ? 'checked' : '' }}>
                                <label for="oriented">Oriented</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $condition_at_inc_sedated ? 'checked' : '' }}>
                                <label for="sedated">Sedated</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $condition_at_inc_disOriented ? 'checked' : '' }}>
                                <label for="disoriented">Disoriented</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $condition_at_inc_other ? 'checked' : '' }}>
                                <label for="other">Other (Specify)</label><br/><br/>
                                <u style="margin-left:15px">{{ !empty($condition_at_inc_other_text) ? $condition_at_inc_other_text : "" }}</u>
                            </div>
                        </div>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Fall Assessment </p>
                        <div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_mediChange ? 'checked' : '' }}>
                                <label for="medicationChange">Medication Change</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_cardMedi ? 'checked' : '' }}>
                                <label for="cardiacMedications">Cardiac Medications</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_moodAltMedi ? 'checked' : '' }}>
                                <label for="moodAlteringMedications">Mood Altering Medications</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_visDef ? 'checked' : '' }}>
                                <label for="visualDeficit">Visual Deficit</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_relocation ? 'checked' : '' }}>
                                <label for="relocation">Relocation</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $fall_assess_tempIllness ? 'checked' : '' }}>
                                <label for="temporaryIllness">Temporary Illness</label>
                            </div>
                        </div>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Ambulation</p>
                        <div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_unlimited ? 'checked' : '' }}>
                                <label for="unlimited">Unlimited</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_limited ? 'checked' : '' }}>
                                <label for="limited">Limited</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_reqAssist ? 'checked' : '' }}>
                                <label for="requiredAssistance">Required assistance</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_wheelChair ? 'checked' : '' }}>
                                <label for="wheelchair">Wheelchair</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_walker ? 'checked' : '' }}>
                                <label for="walker">Walker</label>
                            </div>
                            <div class="checkbox-label">
                                <input type="checkbox" name="admin" value="yes" {{ $ambulation_other ? 'checked' : '' }}>
                                <label for="other">Other (Specify)</label>
                            </div>
                            <br>
                            <u>{{ !empty($ambulation_other_text) ? $ambulation_other_text : "" }}</u>
                        </div>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Fire</p>
                        <div style="text-align: left; margin-left: 80px;">
                            <span>
                                <label for="witness-yes">Yes</label>
                            </span>
                            <span> 
                                <label for="witness-no">No</label>
                            </span>
                        </div>
                        <div style="width: 100%;">
                           <div class="checkbox-label">
                                <label for="alarmPulled" style="margin-right:10px">Alarm pulled</label>
                                <input type="checkbox" name="admin" value="yes" {{ $fire_alarm_pulled == "Yes" ? 'checked' : '' }}> 
                                <input type="checkbox" name="admin" value="yes" {{ $fire_alarm_pulled == "No" ? 'checked' : '' }}> 
                            </div>
                            <div class="checkbox-label">
                                <label for="falseAlarm" style="margin-right:10px">False alarm</label>
                                <input type="checkbox" name="admin" value="yes" {{ $fire_false_alarm == "Yes" ? 'checked' : '' }}> 
                                <input type="checkbox" name="admin" value="yes" {{ $fire_false_alarm == "No" ? 'checked' : '' }}>  
                            </div>
                            <div class="checkbox-label">
                                <label for="extinguisherUsed" style="margin-right:1px">Extinguisher used</label>
                                <input type="checkbox" name="admin" value="yes" {{ $fire_extinguisher_used == "Yes" ? 'checked' : '' }}> 
                                <input type="checkbox" name="admin" value="yes" {{ $fire_extinguisher_used == "No" ? 'checked' : '' }}>
                            </div>
                            <div class="checkbox-label">
                                <label for="personalnjury" style="margin-right:10px">Personal injury</label>
                                <input type="checkbox" name="admin" value="yes" {{ $fire_personal_injury == "Yes" ? 'checked' : '' }}> 
                                <input type="checkbox" name="admin" value="yes" {{ $fire_personal_injury == "No" ? 'checked' : '' }}> 
                            </div>
                            
                            <div class="checkbox-label">
                                <label for="residentProperty" style="margin-right:10px">Resident or facility property damage</label>
                                <input type="checkbox" name="admin" value="yes" {{ $fire_property_damage == "Yes" ? 'checked' : '' }}> 
                                <input type="checkbox" name="admin" value="yes" {{ $fire_property_damage == "No" ? 'checked' : '' }}>
                            </div>
                        </div>
                    </div>
                </th>
            </tr>
            <tr>
                <th colspan="4">
                    <strong>FACTUAL CONCISE DESCRIPTION OF INCIDENT, INJURY, AND ACTION TAKEN:</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{$factual_description}}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            
            <br />
            <br />
            <br />
            
        
            <p>Attachments:-</p>
            
            <hr/>
            @foreach ($images as  $image)
                
                    <img src={{ $image }} widht="20%" height="20%"/>
                    
                    <br /><br /><br />
                    <hr/>
                    <hr/>
                    
                
            @endforeach
     
                
            <br />
            <br />
            <br />
            <br />
            <hr/>
            <tr>
                <th colspan="4">
                    <strong>NOTIFICATION</strong>
                </th>
            </tr>
            <tr>
                <th colspan="2">
                    <p><span>INFORMED OF INCIDENT</span> <span style="margin-left: 33%;">INITIAL</span> </p>

                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="border: none; text-align: left;">
                                <div style="display: flex; flex-direction: column;">
                                    <div class="checkbox-label" style="width: 230px;">
                                        <input type="checkbox" name="admin" value="yes" {{ $informed_of_inc_AGM ? 'checked' : '' }}>
                                        <label for="AGM">Assistant General Manager</label>
                                    </div>
                                    <div class="checkbox-label" style="width: 230px;">
                                        <input type="checkbox" name="admin" value="yes" {{ $informed_of_inc_GM ? 'checked' : '' }}>
                                        <label for="GM">General Manager</label>
                                    </div>
                                    <div class="checkbox-label" style="width: 230px;">
                                        <input type="checkbox" name="admin" value="yes" {{ $informed_of_inc_RMC ? 'checked' : '' }}>
                                        <label for="RMC">Risk Management Committee</label>
                                    </div>
                                    <div class="checkbox-label" style="width: 230px;">
                                        <input type="checkbox" name="admin" value="yes" {{ $informed_of_inc_other ? 'checked' : '' }}>
                                        <label for="other">Other <u>{{ !empty($informed_of_inc_other_text) ? $informed_of_inc_other_text : "" }}</u></label>
                                    </div>
                                </div>
                            </td>
                            <td style="border: none; text-align: left;">
                                <div style="display: flex; flex-direction: column;">
                                    <div>
                                        <p style="padding-top:1pt"><u>{{ !empty($initial_assistant_gm) ? $initial_assistant_gm : "" }}</u></p>
                                    </div>
                                    <div>
                                        <p style="padding-top:1pt"><u>{{ !empty($initial_gm) ? $initial_gm : "" }}</u></p>
                                    </div>
                                    <div>
                                        <p style="padding-top:1pt"><u>{{ !empty($initial_risk_mng_committee) ? $initial_risk_mng_committee : "" }}</u></p>
                                    </div>
                                    <div>
                                        <p style="padding-top:1pt">{{ !empty($initial_other) ? $initial_other : "" }}</p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </th>
                <th>
                    <p>PERSON NOTIFIED</p>
                    <div style="width: 100%;">
                        <div>
                            <p>Family Doctor:<u>{{ !empty($notified_family_doctor) ? $notified_family_doctor : ""  }}</u></p>
                        </div>
                        <div>
                            <p>Time: <u>{{ !empty($notified_family_doctor_tm) ? $notified_family_doctor_tm : "" }}</u></p>
                        </div> 
                        <div>
                            <p>Other: <u>{{ !empty($notified_other) ? $notified_other : "" }}</u></p>
                        </div>
                        <div>
                            <p>Time: <u>{{ !empty($notified_other_tm) ? $notified_other_tm : "" }}</u></p>
                        </div>
                    </div>
                </th>
                <th>
                    <p style="">NOTIFIED RESIDENT'S RESPONSIBLE PARTY</p>
                   
                    <div style="width: 100%;">
                        <div class="checkbox-label" style="margin-left: 30px;">
                            <div class="checkbox-group">
                                <input type="checkbox" name="admin" value="yes" {{ $notified_resident_responsible_party == "Yes" ? 'checked' : '' }}> 
                                <label for="yes" style="margin-right:10px">Yes</label>
                                <input type="checkbox" name="admin" value="yes" {{ $notified_resident_responsible_party == "No" ? 'checked' : '' }}> 
                                <label for="no">No</label>
                            </div>
                        </div>
                    </div>
                    <div>
                        <p>Name: <u>{{ !empty($notified_resident_name) ? $notified_resident_name : ""  }}</u></p>
                    </div>
                    <div>
                        <p>Date: <u>{{ !empty($notified_resident_dt) ?  $notified_resident_dt : ""}}</u></p>
                    </div> 
                    <div>
                        <p>Time: <u>{{ !empty($notified_resident_tm) ? $notified_resident_tm : ""}}</u></p>
                    </div>
                </th>
            </tr>
            <tr>
                <th colspan="2">
                    <div>
                        <p style="margin-top: 0px;">COMPLETED BY</p> 
                        {{ $completed_by }}
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">POSITION</p>
                        {{ $completed_position }}
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE</p>
                        {{ $completed_date }}
                    </div>
                </th>
            </tr>
        </table>
    </div>
    <div class="container">
        <div class="row" style="text-align: center; margin-top: 50px;">
            <div>
                <h1 style="">FOLLOW UP <span style="color: black; font-weight: bold;">(For Management Use Only)</span></h1>
            </div>
        </div>
        <table>
            
            <tr>
                <th colspan="4">
                    <strong>Follow up done by</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ !empty($followUp_done_by) ? $followUp_done_by : ""  }}
                </th>
            </tr>
            
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            
            <tr>
                <th colspan="4">
                    <strong>ISSUE (Problem)</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ $followUp_issue }}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4">
                    <strong>FINDINGS (Gather Information)</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ $followUp_findings }}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4">
                    <strong>POSSIBLE SOLUTIONS (Identify Solution)</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ $followUp_possible_solutions }}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4">
                    <strong>ACTION PLAN</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ $followUp_action_plan }}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
             <tr>
                <th colspan="4">
                    <strong>FOLLOW UP (Examine Result – Did the Plan work?)</strong>
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                    {{ $followUp_examine_result }}
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            <tr>
                <th colspan="4" style="height: 20px;">
                </th>
            </tr>
            
        </table>
    </div>
</body>
</html>