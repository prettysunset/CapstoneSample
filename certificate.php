<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Formal Certificate Generator</title>
  <style>
    body {
      font-family: "Times New Roman", serif;
      background: #f4f4f4;
      padding: 20px;
      text-align: center;
    }

    .form-container {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      margin-bottom: 20px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }

    .form-container input, 
    .form-container textarea {
      width: 100%;
      padding: 8px;
      margin: 6px 0;
      border: 1px solid #ccc;
      border-radius: 6px;
      font-size: 16px;
    }

    .form-container button {
      background: #004080;
      color: #fff;
      border: none;
      padding: 10px 18px;
      font-size: 16px;
      border-radius: 8px;
      cursor: pointer;
      margin-top: 10px;
    }

    .certificate {
      width: 900px;
      height: 650px;
      background: #fff;
      border: 10px solid #2c3e50;
      margin: auto;
      padding: 40px;
      box-sizing: border-box;
      position: relative;
    }

    .certificate h1 {
      font-size: 40px;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 2px;
      color: #2c3e50;
    }

    .certificate h2 {
      font-size: 28px;
      margin: 10px 0;
      color: #000;
    }

    .certificate p {
      font-size: 18px;
      margin: 8px 0;
      color: #333;
    }

    .recipient {
      font-size: 32px;
      font-weight: bold;
      margin: 20px 0;
      text-decoration: underline;
    }

    .signatures {
      display: flex;
      justify-content: space-between;
      margin-top: 50px;
      padding: 0 40px;
    }

    .sig-block {
      text-align: center;
    }

    .sig-block hr {
      width: 200px;
      border: 0;
      border-top: 1px solid #000;
      margin-bottom: 5px;
    }

    @media print {
      body * {
        visibility: hidden;
      }
      .certificate, .certificate * {
        visibility: visible;
      }
      .certificate {
        margin: 0;
        box-shadow: none;
        border: 10px solid #000;
      }
    }
  </style>
</head>
<body>

  <div class="form-container">
    <h2>Certificate Generator</h2>
    <input type="text" id="recipient" placeholder="Buong Pangalan ng Tatanggap">
    <input type="text" id="title" placeholder="Uri ng Sertipiko (e.g. Certificate of Appreciation)">
    <textarea id="reason" placeholder="Ilagay ang dahilan o parangal"></textarea>
    <input type="text" id="date" placeholder="Petsa (e.g. September 2, 2025)">
    <input type="text" id="venue" placeholder="Lugar o Okasyon">
    <input type="text" id="sig1" placeholder="Pangalan at Posisyon ng Unang Lumagda">
    <input type="text" id="sig2" placeholder="Pangalan at Posisyon ng Ikalawang Lumagda">
    <button onclick="generateCertificate()">Ipakita sa Preview</button>
    <button onclick="window.print()">I-print</button>
  </div>

  <div class="certificate" id="certificate">
    <h1 id="certTitle">CERTIFICATE OF APPRECIATION</h1>
    <p>This Certificate is proudly presented to</p>
    <div class="recipient" id="certRecipient">Juan Dela Cruz</div>
    <p id="certReason">For his outstanding contribution and dedication.</p>
    <p id="certVenue">Given this 2nd day of September 2025 at Malolos City.</p>

    <div class="signatures">
      <div class="sig-block">
        <hr>
        <p id="certSig1">__________________<br>Authorized Signatory</p>
      </div>
      <div class="sig-block">
        <hr>
        <p id="certSig2">__________________<br>Authorized Signatory</p>
      </div>
    </div>
  </div>

  <script>
    function generateCertificate() {
      document.getElementById("certRecipient").innerText = document.getElementById("recipient").value || "Juan Dela Cruz";
      document.getElementById("certTitle").innerText = document.getElementById("title").value || "Certificate of Appreciation";
      document.getElementById("certReason").innerText = document.getElementById("reason").value || "For his outstanding contribution and dedication.";
      document.getElementById("certVenue").innerText = 
        "Given this " + (document.getElementById("date").value || "2nd of September 2025") +
        " at " + (document.getElementById("venue").value || "Malolos City") + ".";
      document.getElementById("certSig1").innerText = document.getElementById("sig1").value || "Authorized Signatory";
      document.getElementById("certSig2").innerText = document.getElementById("sig2").value || "Authorized Signatory";
    }
  </script>

</body>
</html>
