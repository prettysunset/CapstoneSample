<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Table Example</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      padding: 20px;
    }
    form {
      margin-bottom: 20px;
    }
    table {
      border-collapse: collapse;
      width: 100%;
    }
    th, td {
      border: 1px solid #ddd;
      padding: 8px;
      text-align: center;
    }
    th {
      background-color: #f2f2f2;
    }
    img {
      width: 75px;   /* approx 2cm */
      height: 75px;  /* keep square */
      object-fit: cover;
      border-radius: 5px;
    }
  </style>
</head>
<body>

  <h2>Add Student</h2>
  <form id="studentForm">
    <input type="text" id="name" placeholder="Name" required>
    <input type="text" id="school" placeholder="School" required>
    <input type="text" id="department" placeholder="Department" required>
    <input type="file" id="photo" accept="image/*" required>
    <button type="submit">Add to Table</button>
  </form>

  <table id="studentTable">
    <thead>
      <tr>
        <th>Photo</th>
        <th>Name</th>
        <th>School</th>
        <th>Department</th>
      </tr>
    </thead>
    <tbody>
      <!-- rows will appear here -->
    </tbody>
  </table>

  <script>
    document.getElementById("studentForm").addEventListener("submit", function(e) {
      e.preventDefault();

      const name = document.getElementById("name").value;
      const school = document.getElementById("school").value;
      const department = document.getElementById("department").value;
      const photo = document.getElementById("photo").files[0];

      if (photo) {
        const reader = new FileReader();
        reader.onload = function(e) {
          const table = document.getElementById("studentTable").querySelector("tbody");
          const row = document.createElement("tr");

          row.innerHTML = `
            <td><img src="${e.target.result}" alt="Photo"></td>
            <td>${name}</td>
            <td>${school}</td>
            <td>${department}</td>
          `;
          table.appendChild(row);
        };
        reader.readAsDataURL(photo);
      }

      // clear form
      this.reset();
    });
  </script>

</body>
</html>
