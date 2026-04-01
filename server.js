// 1. Import packages
require("dotenv").config(); // Loads our hidden passwords from the .env file
const express = require("express");
const path = require("path");
const mysql = require("mysql2");

// 2. Initialize the application
const app = express();
const PORT = process.env.PORT || 3000;

// 3. Set EJS as our Template Engine
app.set("view engine", "ejs");
app.set("views", path.join(__dirname, "views"));

// 4. Serve static files
app.use(express.static(path.join(__dirname, "public")));

// 5. Set up the MySQL Database Connection using Environment Variables
const db = mysql.createConnection({
  host: process.env.DB_HOST,
  user: process.env.DB_USER,
  password: process.env.DB_PASS,
  database: process.env.DB_NAME,
});

// Connect to the database
db.connect((err) => {
  if (err) {
    console.error("Error connecting to the remote database:", err.message);
    return;
  }
  console.log("Successfully connected to the Hostinger MySQL database!");
});

// ... Keep the rest of your routes (app.get) and app.listen unchanged below this line ...

// 6. Update our route to fetch data from MySQL
app.get("/", (req, res) => {
  // This tells MySQL to select everything from a table named "businesses"
  const query = "SELECT * FROM businesses";

  db.query(query, (err, results) => {
    if (err) {
      console.error("Database query error:", err.message);
      res.status(500).send("Error retrieving businesses from the database.");
      return;
    }

    // Pass the database results to our EJS template
    res.render("index", { businesses: results });
  });
});

// 7. Start the server
app.listen(PORT, () => {
  console.log(`Server is running and listening on http://localhost:${PORT}`);
});
