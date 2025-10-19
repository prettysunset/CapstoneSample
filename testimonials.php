<html>
    <head>
        <meta charset="UTF-8" />
        <link rel="stylesheet" href="stylenibilog.css" />
        <title>TESTIMONIALS</title>
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;900&display=swap" rel="stylenibilog.css">
    <style>
        body {
      font-family: 'Poppins', sans-serif;
      margin: 0;
      padding: 0;
      background-color: #e6f2ff;
      color: #333;
    }
    .navbar {
    display: flex;
    justify-content: space-between; /* logo left, links right */
    align-items: center;           /* vertical alignment */
    margin: 22px 33px;             /* top-bottom: 22px, left-right: 33px */
}

.logo {
    font-weight: bold;
    text-decoration: none;
    color: #3a4163;
}

.logo:hover {
    background: none;
    color: #3a4163; /* no hover effect */
    padding: 0;
}

.nav-links {
    display: flex;
    list-style: none;
    gap: 15px;
    margin: 0;  /* reset default */
    padding: 0;
    align-items: center;
}

.nav-links li {
    cursor: pointer;
    padding: 5px 15px;
}

.nav-links a {
    text-decoration: none;
    color: #3a4163;
}

/* Hover for normal links (exclude logo & login) */
.nav-links a:hover {
    background-color: #3a4163;
    color: white;
    border-radius: 15px;
    padding: 5px 15px;
}

/* Login button */
.navbar li.login a {
    color: white;
    text-decoration: none;
    display: inline-block;
    padding: 5px 15px;
    background-color: #3a4163;
    border-radius: 15px;
        font-weight: bold;
    transition: background-color 0.3s;
}

.navbar li.login a:hover {
    background-color: #2a2f4f;

}
 h1 {
      text-align: center;
      padding: 25px 20px;
      color: #2e3560;
      font-weight: 700;
    }

.stories-section{
    display: flex;
    flex-direction: row;
    align-items: flex-start;
    gap: 30px;
    padding: 20px;
    justify-content: center;
    flex-wrap: wrap;
}

.story{
    background-color: white;
    border-radius: 15px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    padding: 20px;
    max-width: 600px;
    text-align: left;
    display: flex;
    flex-direction: column;
    gap: 10px;
    align-items: flex-start;
}

/* If the first .story contains only the image, remove its box so the image isn't boxed */
.stories-section > .story:first-child{
    background: transparent;
    box-shadow: none;
    padding: 0;
    max-width: unset;
}

/* Image styling (no box) */
.story img{
    width: 150px;
    height: 150px;
    border-radius: 15%;
    object-fit: cover;
    display: block;
    margin: 0;
    box-shadow: none;
    background: transparent;
}

/* Ensure text panel keeps the card look */
.stories-section > .story:last-child{
    max-width: 600px;
}

/* Headings and paragraph */
.story h2 {
    color: #3a4163;
    margin-bottom: 10px;
}

.story h4 {
    color: #4a6ff3;
    margin-bottom: 15px;
}

.story p {
    font-size: 16px;
    line-height: 1.5;
    color: #555;
}

</style>
    
    
    </head>
    <body>
        <div class="navbar">
        <h1><a class="logo" href="about.php">OJT-MS</a></h1>

        <div class="nav-links">
        <li><a href="home.php">Home</a></li>
        <li><a>About</a></li>
        <li><a href="contacts.php">Contacts</a></li>
        <li><a href="offices.php">Offices</a></li>
        <li class="login"><a href="login.php">Login</a></li>
        </div>
    </div>
    
    <h1 style="padding-top: 0%; color: #3a4163;">Stories from Our <span style="color: #4a6ff3;">Interns</span></h1>

    <div class="stories-section">
        <div class="story" style="max-width:600px; height:320px;">
            <img src="Untitled design (70).png" alt="picofinterns" style="width:100%; height:100%; object-fit:cover; border-radius:15%;">
        </div>
        <div class="story">
            <h2 style="padding:0;">Santiago, Jasmine M.</h2>
            <h4 style="padding:0; color: #4a6ff3;">BS in Information Technology, BulSU</h4>
            <p>"My OJT at Malolos City Hall gave me the chance to <br>apply what I learned in school to actual office tasks.<br> 
                I became more confident in handling clerical work <br>and assisting people."</p>
        </div>
    </div>

    <div class="stories-section" style="flex-direction: row-reverse;">
        <div class="story" style="max-width:600px; height:320px;">
            <img src="Untitled design (80).png" alt="picofinterns" style="width:100%; height:100%; object-fit:cover; border-radius:15%;">
        </div>
        <div class="story">
            <h2 style="padding:0;">Sayo, John Paul D.</h2>
            <h4 style="padding:0; color: #4a6ff3;">BS in Information System, BPC</h4>
            <p>“During my OJT at Malolos City Hall, I was able to<br> experience real office operations. It improved my<br> skills in organizing documents and communicating<br> with different people.”</p>
        </div>
    </div>

    <div class="stories-section">
        <div class="story" style="max-width:600px; height:320px;">
            <img src="Untitled design (75).png" alt="picofinterns" style="width:100%; height:100%; object-fit:cover; border-radius:15%;">
        </div>
        <div class="story">
            <h2 style="padding:0;">Robles, Jenny</h2>
            <h4 style="padding:0; color: #4a6ff3;">BS in Information Technology, BulSU</h4>
            <p>“During my OJT at Malolos City Hall, I applied what I <br>learned in school, improved my communication,<br> and gained valuable experience in public service.”</p>
        </div>
    </div>

    <div class="stories-section" style="flex-direction: row-reverse;">
        <div class="story" style="max-width:600px; height:320px;">
            <img src="Untitled design (90).png" alt="picofinterns" style="width:100%; height:100%; object-fit:cover; border-radius:15%;">
        </div>
        <div class="story">
            <h2 style="padding:0;">Bautista, Jaime Jr. DC.</h2>
            <h4 style="padding:0; color: #4a6ff3;">BS in Information System, BPC</h4>
            <p>“My OJT at Malolos City Hall gave me real office<br> experience, improved my organizational skills, and <br>boosted my confidence in dealing with people.”</p>
        </div>
    </div>

    </body>
    </html>