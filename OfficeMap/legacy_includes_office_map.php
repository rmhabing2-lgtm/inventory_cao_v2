<?php
// Migrated legacy include moved into OfficeMap folder.
// Original file: includes/office_map.php
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Office Interior Map (legacy)</title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
  <style>
    body { margin:0; font-family: Arial, Helvetica, sans-serif; }
    #map { width: 100%; height: calc(100vh - 56px); }
    .topbar { height:56px; padding:8px 16px; background:#f4f6f8; display:flex; align-items:center; gap:12px; }
    .btn { padding:6px 10px; border:1px solid #bbb; background:#fff; cursor:pointer; }
    table { border-collapse:collapse; width:100%; }
    table td, table th { border:1px solid #ddd; padding:6px; }
  </style>
</head>
<body>
  <div class="topbar">
    <strong>Office Map (legacy)</strong>
    <button class="btn" onclick="exportData()">Export JSON</button>
    <button class="btn" onclick="resetData()">Reset Sample Data</button>
    <div style="margin-left:12px;color:#666;">Click a desk to see items, holder and accountable person.</div>
  </div>
  <div id="map"></div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
    // Minimal legacy map JS (kept as reference)
    const imageUrl = '/inventory_cao_v2/OfficeMap/officemap.PNG';
    let imgW = 2000, imgH = 1200;
    const sampleDesks = [];
    function initMap(){ const map = L.map('map', { crs: L.CRS.Simple }); L.imageOverlay(imageUrl, [[0,0],[imgH,imgW]]).addTo(map); map.fitBounds([[0,0],[imgH,imgW]]); }
    initMap();
  </script>
</body>
</html>
