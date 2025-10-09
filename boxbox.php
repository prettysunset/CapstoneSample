<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Box Box Example</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background: #ffe6f0;
      padding: 20px;
    }

    form {
      margin-bottom: 20px;
    }

    input[type="text"] {
      padding: 8px;
      border: 2px solid #ff66a3;
      border-radius: 8px;
      width: 250px;
    }

    button {
      padding: 8px 15px;
      border: none;
      background: #ff66a3;
      color: white;
      border-radius: 8px;
      cursor: pointer;
    }

    button:hover {
      background: #e05590;
    }

    .cards-container {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
    }

    .card {
      background: white;
      border: 2px solid #ff66a3;
      border-radius: 12px;
      padding: 15px;
      width: 180px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .card h3 {
      margin: 0;
      color: #ff3399;
      font-size: 18px;
    }
  </style>
</head>
<body>
  <h2>Student Cards Demo</h2>
  
  <form id="studentForm">
    <input type="text" id="studentName" placeholder="Enter student name">
    <button type="submit">Add</button>
  </form>

  <div class="cards-container" id="cardsContainer"></div>

  <script>
    const form = document.getElementById('studentForm');
    const input = document.getElementById('studentName');
    const container = document.getElementById('cardsContainer');

    form.addEventListener('submit', function(e) {
      e.preventDefault();

      const name = input.value.trim();
      if(name === "") return;

      // create box
      const card = document.createElement('div');
      card.className = 'card';
      card.innerHTML = `<h3>${name}</h3><p>Status: Pending</p>`;

      container.appendChild(card);

      // clear input
      input.value = "";
    });
  </script>
</body>
</html>
