<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Submitted | OJTMS</title>
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f9fafc;
      margin: 0;
      padding: 0;
      display: flex;
    }

    .left-section {
      width: 35%;
      background-color: #e8f1ff;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 40px;
    }

    .left-section img {
      width: 180px;
      margin-bottom: 20px;
    }

    .left-section h2 {
      margin: 0;
      font-size: 22px;
      color: #1e1e2f;
      font-weight: 600;
    }

    .right-section {
      width: 65%;
      background-color: #fff;
      border-radius: 20px 0 0 20px;
      padding: 50px 80px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    /* Progress Bar */
    .progress-container {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 80px;
      margin-bottom: 40px;
      color: #888;
      font-weight: 500;
      font-size: 14px;
    }

    .progress-step {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .progress-step .circle {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      border: 2px solid #555;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 8px;
    }

    .progress-step.completed .circle {
      background-color: #2b2b6d;
      color: #fff;
      border-color: #2b2b6d;
    }

    .progress-step.completed span {
      color: #2b2b6d;
    }

    /* Center Content */
    .content {
      text-align: center;
      max-width: 450px;
    }

    .content img {
      width: 90px;
      margin-bottom: 20px;
    }

    .content h2 {
      color: #2b2b6d;
      font-size: 22px;
      margin-bottom: 10px;
    }

    .content p {
      font-size: 15px;
      color: #333;
      line-height: 1.6;
    }

    .btn-home {
      background-color: #2b2b6d;
      color: #fff;
      border: none;
      padding: 10px 30px;
      border-radius: 25px;
      margin-top: 25px;
      font-size: 15px;
      cursor: pointer;
      transition: background 0.3s ease;
    }

    .btn-home:hover {
      background-color: #42427f;
    }
  </style>
</head>
<body>

  <div class="left-section">
    <img src="your_logo.png" alt="OJTMS Logo">
    <h2>OJT APPLICATION FORM</h2>
  </div>

  <div class="right-section">
    <div class="progress-container">
      <div class="progress-step completed">
        <div class="circle">✓</div>
        <span>Personal Information</span>
      </div>
      <div class="progress-step completed">
        <div class="circle">✓</div>
        <span>School Information</span>
      </div>
      <div class="progress-step completed">
        <div class="circle">✓</div>
        <span>Requirements</span>
      </div>
    </div>

    <div class="content">
      <img src="https://cdn-icons-png.flaticon.com/512/845/845646.png" alt="Check">
      <h2>Application Submitted</h2>
      <p>Thank you for submitting your OJT application.<br>
      Your request has been successfully received.<br>
      Please wait for an email notification from the<br>
      HR Head regarding approval status.</p>
      <button class="btn-home" onclick="window.location.href='index.php'">Back to Home Page</button>
    </div>
  </div>

</body>
</html>
