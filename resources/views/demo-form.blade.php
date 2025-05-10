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
            justify-content: flex-start; 
            align-items: center; 
            margin: 10px 0; 
        }
        .checkbox-group {
            display: flex;
            justify-content: flex-start; 
            margin-left: 10px; 
        }          
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
        <div>
            <input type="checkbox" id="resident"> 
            <label for="resident">Resident</label>
        </div>
        <div>
            <input type="checkbox" id="visitor"> 
            <label for="visitor">Visitor</label>
        </div>
        <div>
            <input type="checkbox" id="staff"> 
            <label for="staff">Staff</label>
        </div>
        <div>
            <input type="checkbox" id="other"> 
            <label for="other">Other ______________________</label>
        </div>
    </div>

    <div class="container">
        <table>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE OF INCIDENT (D/M/Y)</p>
                    </div>
                    <div style="text-align: center">//</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Time</p>
                    </div>
                    <div style="text-align: center">:</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">LOCATION OF INCIDENT</p>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">WITNESSED BY</p>
                    </div>
                </th>
            </tr>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE OF DISCOVERY (D/M/Y)</p>
                    </div>
                    <div style="text-align: center">//</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Time</p>
                    </div>
                    <div style="text-align: center">:</div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">LOCATION OF DISCOVERY</p>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DISCOVERED BY</p>
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
                                    <div class="checkbox-container" style="display: block;"> 
                                        <input type="checkbox" id="fall">
                                        <label for="fall" style="padding-bottom: 10px;">Fall</label>
                                    </div>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="fire">
                                        <label for="fire">Fire</label>
                                    </div>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="security">
                                        <label for="security">Security</label>
                                    </div>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="elopement">
                                        <label for="elopement">Elopement</label>
                                    </div>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="aggressive">
                                        <label for="aggressive">Aggressive Behavior</label>
                                    </div>
                                    <div class="checkbox-container">
                                        <input type="checkbox" id="other-type">
                                        <label for="other-type">Other _______</label>
                                    </div>                                  
                                </div>
                            </td>
                            <td style="border: none; text-align: left;">
                                <div>
                                    <input type="checkbox" id="resident-abuse"> 
                                    <label for="resident-abuse">Resident Abuse</label><br>
                                    <input type="checkbox" id="treatment"> 
                                    <label for="treatment">Treatment</label><br>
                                    <input type="checkbox" id="loss-property"> 
                                    <label for="loss-property">Loss of Property</label><br>
                                    <input type="checkbox" id="choking"> 
                                    <label for="choking">Choking</label><br>
                                    <input type="checkbox" id="death"> 
                                    <label for="death">Death</label>
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
                                <input type="checkbox" id="fob-yes"> 
                                <input type="checkbox" id="fob-no"> 
                                <input type="checkbox" id="fob-na"> 
                                
                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Call bell within reach</span>
                            <div class="checkbox-group">
                                <input type="checkbox" id="bell-yes"> 
                                <input type="checkbox" id="bell-no"> 
                                <input type="checkbox" id="bell-na"> 
                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Caution signs in place</span>
                            <div class="checkbox-group">
                                <input type="checkbox" id="signs-yes">
                                <input type="checkbox" id="signs-no"> 
                                <input type="checkbox" id="signs-na"> 

                            </div>
                        </div>
                        <div class="checkbox-label">
                            <span>Other___________</span>
                        </div>
                    </div>
                </th>

                <th>
                    <p style="margin-top: 0px;">OTHER WITNESSES?</p>

                    <div style="width: 100%;">
                        <div class="checkbox-label" style="margin-left: 30px;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="witness-yes"><span style="font-size: 14px; margin-left: 5px; margin-top: 3px;">Yes</span>
                                <input type="checkbox" id="witness-no"> <span  style="font-size: 14px; margin-left: 5px; margin-top: 3px;">No</span>
                                
                            </div>
                        </div>
                    </div>

                    <div style="width: 100%;">
                        <div><p>Name:__________</p></div>
                        <div><p>Position:__________</p></div>
                        <div><p>Name:__________</p></div>
                        <div><p>Position:__________</p></div>
                    </div>
                </th>
            </tr>
            <tr>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Condition At Time Of Incident</p>
                        <div>
                            <input type="checkbox" id="oriented"> 
                            <label for="oriented">Oriented</label><br>
                            <input type="checkbox" id="sedated"> 
                            <label for="sedated">Sedated</label><br>
                            <input type="checkbox" id="disoriented"> 
                            <label for="disoriented">Disoriented </label><br>
                            <input type="checkbox" id="other"> 
                            <label for="other">Other (Specify)</label><br>
                        </div>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Fall Assessment </p>
                        <div>
                            <input type="checkbox" id="medicationChange"> 
                            <label for="medicationChange">Medication Change</label><br>
                            <input type="checkbox" id="cardiacMedications"> 
                            <label for="cardiacMedications">Cardiac Medications</label><br>
                            <input type="checkbox" id="moodAlteringMedications"> 
                            <label for="moodAlteringMedications">Mood Altering Medications</label><br>
                            <input type="checkbox" id="visualDeficit"> 
                            <label for="visualDeficit">Visual Deficit</label><br>
                            <input type="checkbox" id="relocation"> 
                            <label for="relocation ">Relocation</label><br>
                            <input type="checkbox" id="temporaryIllness"> 
                            <label for="temporaryIllness">Temporary Illness</label><br>
                        </div>
                    </div>
                   
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">Ambulation</p>
                        <div>
                            <input type="checkbox" id="unlimited "> 
                            <label for="unlimited ">Unlimited</label><br>
                            <input type="checkbox" id="limited"> 
                            <label for="limited">Limited</label><br>
                            <input type="checkbox" id="requiredAssistance"> 
                            <label for="requiredAssistance">Required assistance</label><br>
                            <input type="checkbox" id="wheelchair"> 
                            <label for="wheelchair">Wheelchair</label><br>
                            <input type="checkbox" id="walker"> 
                            <label for="walker">Walker</label><br>
                            <input type="checkbox" id=""> 
                            <label for="other">Other (Specify)</label><br>
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
                                <span>Alarm pulled </span>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="alarm-yes"> 
                                    <input type="checkbox" id="alarm-no"> 
                                    
                                </div>
                            </div>
                            <div class="checkbox-label">
                                <span>False alarm</span>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="false-yes"> 
                                    <input type="checkbox" id="false-no"> 
                                </div>
                            </div>
                            <div class="checkbox-label">
                                <span>Extinguisher used</span>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="extinguisher-yes">
                                    <input type="checkbox" id="extinguisher-no"> 
                                </div>
                            </div>
                            <div class="checkbox-label">
                                <span>Personal injury</span>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="personal-yes"> 
                                    <input type="checkbox" id="personal-no"> 
                                </div>
                            </div>
                            <div class="checkbox-label">
                                <span>Resident or facility</span>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="resident-yes">
                                    <input type="checkbox" id="resident-no"> 
                                </div>
                            </div>
                            <div class="checkbox-label">
                                <span>property damage</span>
                            </div>
                        </div>
                        <!-- <table style="width: 100%; border: none;">
                            <tr>
                                <td style="border: none; text-align: left;">
                                    <div>
                                       
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <label for="alarmPulled" style="padding-bottom: 10px;">Alarm pulled</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <label for="falseAlarm" style="padding-bottom: 10px;">False alarm</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <label for="extinguisherUsed" style="padding-bottom: 10px;">Extinguisher used</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <label for="personalnjury" style="padding-bottom: 10px;">Personal injury</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <label for="residentProperty" style="padding-bottom: 10px;">Resident or facility  property damage</label>
                                                </div>
                                            </div>
                                        </div>
                                                                       
                                    </div>
                                </td>
                                <td style="border: none; text-align: left;">
                                    <div>                                        
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <input type="checkbox" id="alarmPulled-yes">
                                                    <input type="checkbox" id="alarmPulled-no">                                                 </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <input type="checkbox" id="falseAlarm-yes">
                                                    <input type="checkbox" id="falseAlarm-no">                                                 </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <input type="checkbox" id="alarmPulled-yes">
                                                    <input type="checkbox" id="alarmPulled-no">                                                 </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%;">
                                            <div class="checkbox-label">
                                                <div class="checkbox-group">
                                                    <input type="checkbox" id="alarmPulled-yes">
                                                    <input type="checkbox" id="alarmPulled-no">                                                 </div>
                                            </div>
                                        </div>                                
                                    </div>
                                </td>
                            </tr>
                        </table> -->
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
                    <strong>NOTIFICATION</strong>
                </th>
            </tr>
            <tr>
                <th colspan="2">
                    <p><span>INFORMED OF INCIDENT</span> <span style="margin-left: 33%;">INITIAL</span> </p>

                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="border: none; text-align: left;">
                                <div>
                                    <p for="">Assistant General Manager</p>
                                </div>
                                <div>
                                    <p for="">General Manager</p>
                                </div>
                                <div>
                                    <p for="">Risk Management Committee</p>
                                </div>
                                <div>
                                    <p for="">Other____________________</p>
                                </div>
                            </td>
                            <td style="border: none; text-align: left;">
                                <div>
                                    <div>
                                        <p for="">_______________</p>
                                    </div>
                                    <div>
                                        <p for="">_______________</p>
                                    </div>
                                    <div>
                                        <p for="">_______________</p>
                                    </div>
                                    <div>
                                        <p for="">_______________</p>
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
                            <p>Family Doctor: _</p>
                        </div>
                        <div>
                            <p>Time: _</p>
                        </div> 
                        <div>
                            <p>Other: _</p>
                        </div>
                        <div>
                            <p>Time: _</p>
                        </div>
                    </div>
                </th>
                <th>
                    <p style="">NOTIFIED RESIDENT’S RESPONSIBLE PARTY</p>
                   
                    <div style="width: 100%;">
                        <div class="checkbox-label" style="margin-left: 30px;">
                            <div class="checkbox-group">
                                <input type="checkbox" id="witness-yes"><span style="font-size: 14px; margin-left: 5px; margin-top: 3px;">Yes</span>
                                <input type="checkbox" id="witness-no"> <span  style="font-size: 14px; margin-left: 5px; margin-top: 3px;">No</span>
                                
                            </div>
                        </div>
                    </div>
                    <div>
                        <p>Name: _</p>
                    </div>
                    <div>
                        <p>Date: _</p>
                    </div> 
                    <div>
                        <p>Time: _</p>
                    </div>
                </th>
            </tr>
            <tr>
                <th colspan="2">
                    <div>
                        <p style="margin-top: 0px;">COMPLETED BY</p>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">POSITION</p>
                    </div>
                </th>
                <th>
                    <div>
                        <p style="margin-top: 0px;">DATE</p>
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
                    <strong>ISSUE (Problem)</strong>
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
                    <strong>FINDINGS (Gather Information)</strong>
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
