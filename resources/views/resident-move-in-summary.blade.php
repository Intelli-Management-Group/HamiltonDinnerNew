<html>
    <head>
        <title>RESIDENT MOVE-IN SUMMARY</title>
            <style>
        /* General Styles */
        .container {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
            max-width: 800px;
            margin: 0 auto;
        }
        .rowTopPaddig {
            margin-top: 35px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .justifyBetween {
            justify-content: space-between;s
        }
        .justifyStart {
            justify-content: flex-start;
        }
        .justifyCenter {
            justify-content: center;
        }
        .justifyEnd {
            justify-content: end;
        }
        .logo {
            text-align: left;
        }
        .title {
            text-align: right;
            font-weight: bold;
        }
        .row.justifyStart {
            justify-content: center;
            flex-direction: column;
            text-align: center;
        }
        .row.justifyStart div {
            margin: 5px 0;
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
        } 
        
        
    .name-container {
        display: flex;
        justify-content: space-between;
        margin-top: 10px;
        margin-bottom: 20px;
    }
    
    .name {
        position: relative;
        flex: 1;
    }
    
    .name span {
        display: block;
        margin-bottom: 5px;
        text-align: center;
    }
    
    .underline {
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        border-bottom: 1px solid #000;
    }
    
    .left .underline {
        right: 50%;
    }
    
    .right .underline {
        left: 50%;
    }

        /* Print Styles */
        @media print {
            
             .customsContainer {
                display: table;
                width: 100%;
            }

            .customsContainer div {
                display: table-cell;
                padding: 5px;
                vertical-align: middle; /* Ensure middle alignment when printed */
            }
            
            body {
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
                font-size: 12pt;
                line-height: 1.4;
            }
            .row {
                display: block;
                width: 100%;
            }
            .rowTopPaddig {
                margin-top: 25px;
            }
            .title {
                text-align: left;
                font-size: 14pt;
            }
            .logo img {
                width: 100%;
                height: auto;
                max-width: 150px;
            }
            .row.justifyBetween {
                justify-content: flex-start;
            }
            .row.justifyStart {
                text-align: left;
                font-size: 12pt;
            }
            .justifyStart {
                justify-content: start;
            }
            .row.justifyCenter {
                justify-content: center;
            }
            .justifyEnd {
                justify-content: end !important;
            }
            .row .justifyBetween {
                display: block;
                margin-bottom: 10px;
            }
            .row div {
                margin-bottom: 10px;
            }
            .row span {
                display: inline-block;
                margin-right: 10px;
                margin-bottom: 5px;
            }

            /* Page breaks */
            .page-break {
                page-break-before: always;
            }

            /* Hiding unwanted elements */
            .no-print {
                display: none;
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
            
            .name-container {
                display: flex;
                justify-content: space-between;
                margin-top: 10px;
                margin-bottom: 20px;
            }
            
            .name {
                position: relative;
                flex: 1;
            }
            
            .name span {
                display: block;
                margin-bottom: 5px;
                text-align: center;
            }
            
            .underline {
                position: absolute;
                bottom: 0;
                left: 0;
                width: 100%;
                border-bottom: 1px solid #000;
            }
            
            .left .underline {
                right: 50%;
            }
            
            .right .underline {
                left: 50%;
            }
            
        }

        /* Regular Styles */
        .input {
            width: 200px;
            padding: 5px;
            border: 1px solid #ccc;
        }

        /* Checkbox adjustments */
        .checkbox-group input {
            margin-right: 5px;
        }
    </style>
    </head>
    <body>
        <div class="container">
            <div class="customsContainer justifyBetween">
                <div class="logo">
                    <img src="{{URL::asset('/template-media/resident-move-in-summary-logo.jpg')}}" alt="Logo" height="75" width="150">
                </div>
                <div class="title">
                    RESIDENT MOVE-IN SUMMARY 
                </div>
            </div>
              
            <div class="customsContainer rowTopPaddig">
                <div>
                    <span>Suite Number: <u>{{ !empty($suite_number) ? $suite_number : "" }}</u></span>
                </div>
                <div>
                    <span>Contract Signing Date: {{ !empty($contract_signing_date) ? $contract_signing_date : "" }}</span>
                </div>
                <div>
                    <span>Sales Name: <u>{{ !empty($sales_name) ? $sales_name : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig">
                <div>
                    <span>Contract Term:</span>
                </div>
                <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $contract_term_yearly) }}
                    <label for="resident">Yearly</label>
                </div>
                <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $contract_term_monthly) }}
                    <label for="visitor">Monthly</label>
                </div>
                <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $contract_term_weekly) }}
                    <label for="staff">Weekly</label>
                </div>
                 <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $contract_term_daily) }}
                    <label for="staff">Daily</label>
                </div>
               
            </div>

            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Tenancy Commence Date: </span><span>{{ !empty($tenancy_commence_date) ? $tenancy_commence_date : "" }}</span>
                </div>
                <div>
                    <span>Contract Expiry Date: </span><span>{{ !empty($contract_expiry_date) ? $contract_expiry_date : "" }}</span>
                </div>
            </div>
            
            <div class="customsContainer justifyBetween" >
                <div>
                    <span>1<sup>st</sup> Resident Name:</span>
                </div>
              
                <div style="padding-top:20px;">
                    <span>{{ !empty($first_resident_name_first_name) ? $first_resident_name_first_name : "" }}</span><br/>
                    <span style="">FirstName</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($first_resident_name_middle_name) ? $first_resident_name_middle_name : "" }}</span><br/>
                    <span style="">Middle Name</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($first_resident_name_last_name) ? $first_resident_name_last_name : "" }}</span><br/>
                    <span style="">LastName</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($first_resident_dob) ? $first_resident_dob : "" }}</span><br/>
                    <span>DOB</span>
                </div>
            </div>
            
            <div class="customsContainer justifyBetween" >
                <div>
                    <span>2<sup>nd</sup> Resident Name:</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($second_resident_name_first_name) ? $second_resident_name_first_name : "" }}</span><br/>
                    <span style="">FirstName</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($second_resident_name_middle_name) ? $second_resident_name_middle_name : "" }}</span><br/>
                    <span style="">Middle Name</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($second_resident_name_last_name) ? $second_resident_name_last_name : "" }}</span><br/>
                    <span style="">LastName</span>
                </div>
                <div style="padding-top:20px;">
                    <span>{{ !empty($second_resident_dob) ? $second_resident_dob : "" }}</span><br/>
                    <span>DOB</span>
                </div>
            </div>
            
             <div class="customsContainer rowTopPaddig">
                <div>
                    <span><strong>1<sup>st</sup> Month Payment: </strong></span>
                </div>
                <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $first_month_payment_received) }}
                    <label for="resident">Received cheque #</label>
                    <span><u>{{ !empty($first_month_payment_received_cheque_note) ? $first_month_payment_received_cheque_note : "" }}</u></span>
                </div>
                <div class="checkbox-label">
                    <span>{{ !empty($first_month_payment_received_cheque_date) ? $first_month_payment_received_cheque_date : "" }}</span>
                </div>
                <div class="checkbox-label">
                    <span>Total: $</span>
                    <span><u>{{ !empty($first_month_payment_received_cheque_amount) ? $first_month_payment_received_cheque_amount : "" }}</u></span>
                </div>
              
            </div>

            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Monthly Rate: $</span><span><u>{{ !empty($monthly_rate) ? $monthly_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Care Plan Rate: $</span><span><u>{{ !empty($care_plan_rate) ? $care_plan_rate : "" }}</u></span>
                </div>
                <div>
                    <span>One Time Move in Fee: <u>{{ !empty($one_time_move_in_fee) ? "$ ".$one_time_move_in_fee : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Parking X ( {{ !empty($parking_quantity) ? $parking_quantity : "" }} ): $</span><span><u>{{ !empty($parking_rate) ? $parking_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Scooter X ( {{ !empty($scooter_quantity) ? $scooter_quantity : "" }} ): $</span><span><u>{{ !empty($scooter_rate) ? $scooter_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Window Screen X ( {{ !empty($window_screen_quantity) ? $window_screen_quantity : "" }} ): $</span><span><u>{{ !empty($window_screen_rate) ? $window_screen_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Grab Bar X ( {{ !empty($grab_bar_quantity) ? $grab_bar_quantity : "" }} ): $</span></span><span><u>{{ !empty($grab_bar_rate) ? $grab_bar_rate : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer justifyBetween rowTopPaddig" >
                <div>
                    <span>Others: $</span><span><u>{{ !empty($payment_others_rate) ? $payment_others_rate : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig">
                <div>
                    <span><strong>1<sup>st</sup> Security Deposit: </strong></span>
                </div>
                <div class="checkbox-label">
                    {{ Form::checkbox('admin', 'yes', $security_deposit_received) }}
                    <label for="resident">Received cheque #</label>
                    <span><u>{{ !empty($security_deposit_received_cheque_note) ? $security_deposit_received_cheque_note : "" }}</u></span>
                </div>
                <div class="checkbox-label">
                    <span>{{ !empty($security_deposit_received_cheque_date) ? $security_deposit_received_cheque_date : "" }}</span>
                </div>
                <div class="checkbox-label">
                    <span>Total: $</span>
                    <span><u>{{ !empty($security_deposit_received_cheque_amount) ? $security_deposit_received_cheque_amount : "" }}</u></span>
                </div>
              
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>1/2 Month Rental Deposit for 1<sup>st</sup> Resident: $</span><span><u>{{ !empty($half_month_deposit_for_first_resident_rate) ? $half_month_deposit_for_first_resident_rate : "" }}</u></span>
                </div>
                <div>
                    <span>1/2 Month Care Plan: $</span><span><u>{{ !empty($half_month_care_plan_rate) ? $half_month_care_plan_rate : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>1/2 Month Rental Deposit for 2<sup>nd</sup> Resident: $</span><span><u>{{ !empty($half_month_deposit_for_second_resident_rate) ? $half_month_deposit_for_second_resident_rate : "" }}</u></span>
                </div>
                <div>
                    <span style="margin-right: 100px;">Move In/Out: <u>{{ !empty($move_in_out_rate) ? "$ ".$move_in_out_rate : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Elpas X ( {{ !empty($elpas_quantity) ? $elpas_quantity : "" }} ) : $</span><span><u>{{ !empty($elpas_rate) ? $elpas_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Garage Fob X ( {{ !empty($garage_fob_quantity) ? $garage_fob_quantity : "" }} ) : $</span><span><u>{{ !empty($garage_fob_rate) ? $garage_fob_rate : "" }}</u></span>
                </div>
                <div>
                    <span>Others: $</span></span><span><u>{{ !empty($deposit_others_rate) ? $deposit_others_rate : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer justifyStart rowTopPaddig" style="text-align:left" >
                    <span><strong> Payor Information: </strong></span>
                     <div class="checkbox-label">
                        
                        {{ Form::checkbox('admin', 'yes', $payor_information_PAD) }}
                        <label for="resident">PAD</label>
                    </div>
                      <div class="checkbox-label">
                        {{ Form::checkbox('admin', 'yes', $payor_information_Post_Dated_Cheque) }}
                        <label for="resident">Post Dated-Cheque</label>
                    </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Payor’s Name: </span><span><u>{{ !empty($payor_name) ? $payor_name : "" }}</u></span>
                </div>               
                <div>
                    <span>Bank Name: </span></span><span><u>{{ !empty($bank_name) ? $bank_name : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span>Bank ID # (3 digits): </span><span><u>{{ !empty($bank_ID) ? $bank_ID : "" }}</u></span>
                </div>
                <div>
                    <span>Account Number: </span><span><u>{{ !empty($account_number) ? $account_number : "" }}</u></span>
                </div>
                <div>
                    <span>Transit # (5 digits) </span></span><span><u>{{ !empty($transit) ? $transit : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" >
                <div>
                    <span><strong> Others: </strong></span>              
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" style="text-align:left" >
                     <div class="checkbox-label">
                        {{ Form::checkbox('admin', 'yes', $unit_key) }}
                        <label for="resident">Unit Key</label>
                         <span><u>{{ !empty($unit_key_value) ? $unit_key_value : "" }}</u></span>
                    </div>
                      <div class="checkbox-label">
                        {{ Form::checkbox('admin', 'yes', $elpas_fob) }}
                        <label for="resident">Elpas Fob</label>
                         <span><u>{{ !empty($elpas_fob_value) ? $elpas_fob_value : "" }}</u></span>
                    </div>
                    <div>
                    <span>Resident Signature: </span><span> <img src={{$signature}}  alt="resident sign" height="75" width="150"></span>
                    
                    
                </div>
            </div>
            
            <div class="customsContainer rowTopPaddig" style="text-align:left" >
                     <div class="checkbox-label">
                        {{ Form::checkbox('admin', 'yes', $suite_insurance_copy_received) }}
                        <label for="resident">Suite Insurance Copy Received</label>
                         <span>{{ !empty($suite_insurance_copy_received_date) ? $suite_insurance_copy_received_date : "" }}</span>
                    </div>
                      <div class="checkbox-label">
                        {{ Form::checkbox('admin', 'yes', $suite_insurance_coverage_approved) }}
                        <label for="resident">Suite Insurance Coverage Approved</label>
                        
                    </div>
                    
            </div>
            
            <div class="customsContainer rowTopPaddig">
                <div>
                    <span>Insurance Company Name </span><span><u>{{ !empty($insurance_company_name) ? $insurance_company_name : "" }}</u></span>
                </div>
                <div>
                    <span>Policy Number: </span><span><u>{{ !empty($policy_number) ? $policy_number : "" }}</u></span>
                </div>
            </div>
            
            <div class="customsContainer justifyEnd rowTopPaddig" >
                <div>
                    <span>Reviewed by: </span><span><u>{{ !empty($reviewed_by) ? $reviewed_by : "" }}</u></span>
                </div>
                <div > 
                    <span style="margin-left: 20px;">Date: </span><span>{{ !empty($date) ? $date : "" }}</span>
                </div>
            </div>
            
            
        </div>
    </body>
</html>
