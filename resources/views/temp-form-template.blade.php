<html>

<head>
    <title>Log Form</title>
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
            justify-content: space-between;
        }

        .justifyCenter {
            justify-content: center;
        }

        .justifyEnd {
            justify-content: flex-end;
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
            vertical-align: middle;
            /* Align checkboxes and labels in the middle */
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }

        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 5px;
            /* Space between checkbox and label */
            vertical-align: middle;
            /* Ensures vertical alignment */
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            /* Align items vertically in the center */
            margin-left: 10px;
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
                vertical-align: middle;
                /* Ensure middle alignment when printed */
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

            .row.justifyCenter {
                justify-content: center;
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

        .checkbox-group input {
            margin-right: 5px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justifyBetween">
            <!-- <div class="logo">
                    <img src="./hamilton_Logo.png" alt="Logo" height="150">
                </div> -->
            <div class="title">
                Log Form
            </div>
        </div>
        <div class="customsContainer justifyBetween rowTopPaddig">
            <div>
                <span>Room Number: <u>{{ !empty($room_number) ? $room_number : "" }}</u></span>
            </div>
            <div>
                <span>Resident Name: <u>{{ !empty($resident_name) ? $resident_name : "" }}</u></span>
            </div>
        </div>
        <div class="customsContainer justifyBetween rowTopPaddig">
            <div>
                <span>Logged on: <u>{{ !empty($logged_at) ? $logged_at : "" }}</u></span>
            </div>
            <div>
                <span>Logged by: <u>{{ !empty($logged_by) ? $logged_by : "" }}</u></span>
            </div>
        </div>
        <div class="row justifyBetween rowTopPaddig">
            <div>
                <span>What it is about:</span><span><u>{{ !empty($log_text) ? $log_text : "" }}</u></span>
            </div>

        </div>
        <div class="customsContainer row justifyBetween rowTopPaddig">
            <div>
                <span>Action Taken:</span><span><u>{{ !empty($action_taken) ? $action_taken : "" }}</u></span>
            </div>

        </div>
        <div class="customsContainer row justifyBetween rowTopPaddig">
            <div>
                <span>Follow up
                    required:</span></span><span><u>{{ !empty($follow_up_required) ? $follow_up_required : "" }}</u></span>
            </div>
        </div>
        <div class="customsContainer justifyBetween rowTopPaddig">
            
            <div>
                <span>Completed on: <u>{{ !empty($completed_at) ? $completed_at : "" }}</u></span>
            </div>
            <div>
                <span>Completed by: <u>{{ !empty($completed_by) ? $completed_by : "" }}</u></span>
            </div>
        </div>

    </div>
</body>

</html>