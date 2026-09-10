<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tarik Data Eklaim per Bulan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f0f2f5;
            margin: 0;
            padding: 40px 16px;
        }

        .card {
            max-width: 420px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
            padding: 28px;
        }

        h1 {
            font-size: 20px;
            margin: 0 0 8px;
            color: #1a1a2e;
        }

        p {
            color: #666;
            font-size: 14px;
            margin: 0 0 20px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 6px;
            color: #333;
        }

        input[type="month"] {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 16px;
        }

        button {
            width: 100%;
            padding: 11px;
            font-size: 15px;
            font-weight: bold;
            color: #fff;
            background: #1a73e8;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #1558b0;
        }
    </style>
    <link rel="stylesheet" href="https://netdna.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.css">

</head>

<body>
    <div class="card">
        <h1>Tarik Data AMR DPOx per Bulan</h1>
        <p>Pilih bulan untuk mengekspor data eklaim ke Excel.</p>
        <form method="post" action="<?php echo base_url('amr/export'); ?>">
            <div class="mb-3">
                <label for="bulan" class="form-label">Bulan</label>
                <input type="text" class="form-control" id="bulan" name="bulan" required>
            </div>
            <button type="submit" class="btn btn-primary">Tarik Data</button>
        </form>
    </div>
</body>
<script crossorigin="anonymous" integrity="sha384-xBuQ/xzmlsLoJpyjoggmTEz8OWUFM0/RC5BsqQBDX2v5cMvDHcMakNTNrHIW2I5f" src="https://code.jquery.com/jquery-3.2.1.min.js"></script>
<script type="text/javascript" src="//cdnjs.cloudflare.com/ajax/libs/moment.js/2.9.0/moment-with-locales.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script crossorigin="anonymous" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
<script crossorigin="anonymous" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>

<!-- <script type="text/javascript" src="<?= base_url('public/assets') ?>/js/bootstrap-datetimepicker.min.js"></script> -->
<script>
    $(function() {
        $('#bulan').daterangepicker({
            // opens: 'left',
            locale: {
                format: 'DD/MM/YYYY'
            },
        }, function(start, end, label) {
            console.log("A new date selection was made: " + start.format('YYYY-MM-DD') + ' to ' + end.format('YYYY-MM-DD'));
        });


    });
</script>

</html>