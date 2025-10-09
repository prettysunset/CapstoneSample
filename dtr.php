<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>DTR - September</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #ffe6f0;
      padding: 20px;
      color: #333;
    }

    h2 {
      color: #ff3399;
      text-align: center;
    }

    .buttons {
      text-align: center;
      margin-bottom: 20px;
    }

    button {
      margin: 5px;
      padding: 10px 20px;
      border: none;
      border-radius: 8px;
      background: #ff66a3;
      color: white;
      cursor: pointer;
      font-size: 14px;
    }

    button:hover {
      background: #e05590;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: white;
    }

    th, td {
      border: 1px solid #ff99cc;
      padding: 6px;
      text-align: center;
      font-size: 14px;
    }

    th {
      background: #ffccdd;
    }

    tfoot td {
      font-weight: bold;
      background: #ffe6f0;
    }
  </style>
</head>
<body>

  <h2>Daily Time Record (September)</h2>

  <div class="buttons">
    <button onclick="timeIn('amArrival')">AM Time In</button>
    <button onclick="timeOut('amDeparture')">AM Time Out</button>
    <button onclick="timeIn('pmArrival')">PM Time In</button>
    <button onclick="timeOut('pmDeparture')">PM Time Out</button>
  </div>

  <table id="dtrTable">
    <thead>
      <tr>
        <th>Day</th>
        <th>AM Arrival</th>
        <th>AM Departure</th>
        <th>PM Arrival</th>
        <th>PM Departure</th>
        <th>Hours</th>
        <th>Minutes</th>
      </tr>
    </thead>
    <tbody id="dtrBody">
      <!-- Rows for 30 days -->
    </tbody>
    <tfoot>
      <tr>
        <td colspan="5">Total</td>
        <td id="totalHours">0</td>
        <td id="totalMinutes">0</td>
      </tr>
    </tfoot>
  </table>

  <script>
    const dtrBody = document.getElementById("dtrBody");
    const records = {};

    // Generate 30 fixed rows for September
    for (let day = 1; day <= 30; day++) {
      records[day] = { amArrival: "", amDeparture: "", pmArrival: "", pmDeparture: "", hours: 0, minutes: 0 };
      const row = document.createElement("tr");
      row.innerHTML = `
        <td>${day}</td>
        <td id="amArrival-${day}"></td>
        <td id="amDeparture-${day}"></td>
        <td id="pmArrival-${day}"></td>
        <td id="pmDeparture-${day}"></td>
        <td id="hours-${day}">0</td>
        <td id="minutes-${day}">0</td>
      `;
      dtrBody.appendChild(row);
    }

    function getTodayDay() {
      const today = new Date();
      const month = today.getMonth() + 1; // Sept = 9
      if (month !== 9) {
        alert("⚠️ Demo is for September only.");
        return null;
      }
      return today.getDate();
    }

    function timeIn(type) {
      const day = getTodayDay();
      if (!day) return;
      const now = new Date();
      const time = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      records[day][type] = time;
      document.getElementById(`${type}-${day}`).textContent = time;
    }

    function timeOut(type) {
      const day = getTodayDay();
      if (!day) return;
      const now = new Date();
      const time = now.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
      records[day][type] = time;
      document.getElementById(`${type}-${day}`).textContent = time;
      calculateHours(day);
    }

    function calculateHours(day) {
      const rec = records[day];
      let totalMinutes = 0;

      if (rec.amArrival && rec.amDeparture) {
        totalMinutes += diffMinutes(rec.amArrival, rec.amDeparture);
      }
      if (rec.pmArrival && rec.pmDeparture) {
        totalMinutes += diffMinutes(rec.pmArrival, rec.pmDeparture);
      }

      rec.hours = Math.floor(totalMinutes / 60);
      rec.minutes = totalMinutes % 60;

      document.getElementById(`hours-${day}`).textContent = rec.hours;
      document.getElementById(`minutes-${day}`).textContent = rec.minutes;

      updateTotals();
    }

    function diffMinutes(start, end) {
      const [sh, sm] = start.split(":");
      const [eh, em] = end.split(":");
      const startDate = new Date(0, 0, 0, sh, sm);
      const endDate = new Date(0, 0, 0, eh, em);
      return (endDate - startDate) / 60000;
    }

    function updateTotals() {
      let totalH = 0, totalM = 0;
      for (let day in records) {
        totalH += records[day].hours;
        totalM += records[day].minutes;
      }
      totalH += Math.floor(totalM / 60);
      totalM = totalM % 60;
      document.getElementById("totalHours").textContent = totalH;
      document.getElementById("totalMinutes").textContent = totalM;
    }
  </script>

</body>
</html>
