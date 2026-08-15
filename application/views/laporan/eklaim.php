<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Tarik Data Eklaim per Bulan</title>
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
</head>
<body>
  <div class="card">
    <h1>Tarik Data Eklaim per Bulan</h1>
    <p>Pilih bulan untuk mengekspor data eklaim ke Excel.</p>
    <form method="post" action="<?php echo site_url('laporan/eklaim'); ?>">
      <label for="bulan">Bulan</label>
      <input type="month" id="bulan" name="bulan" required>
      <button type="submit">Tarik Data</button>
    </form>
  </div>
</body>
</html>
