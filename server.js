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

// Add this middleware so Express can read form data
app.use(express.urlencoded({ extended: true }));

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

// 6. Update our route to fetch data from MySQL with filtering and sorting
app.get('/', (req, res) => {
    // Only select 'approved' businesses. 
    // Order them: premium (1) first, paid (2) second, free (3) last.
    const query = `
        SELECT * FROM businesses 
        WHERE status = 'approved' 
        ORDER BY 
            CASE tier 
                WHEN 'premium' THEN 1 
                WHEN 'paid' THEN 2 
                WHEN 'free' THEN 3 
            END ASC
    `;
    
    db.query(query, (err, results) => {
        if (err) {
            console.error('Database query error:', err.message);
            res.status(500).send('Error retrieving businesses from the database.');
            return;
        }
        
        res.render('index', { businesses: results });
    });
});

// --- NEW ROUTE: Show the "Add a Business" form page ---
app.get('/add-business', (req, res) => {
    res.render('add-business');
});

// --- NEW ROUTE: Process the form submission ---
app.post('/add-business', (req, res) => {
    // Extract the data from the submitted form
    const { name, category, address, phone, logo, description } = req.body;

    // We will force 'pending' status and 'free' tier for all new submissions
    const status = 'pending';
    const tier = 'free';

    // Create the SQL query to insert the data
    const query = `
        INSERT INTO businesses (name, category, description, tier, status, logo, address, phone) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `;
    
    // The question marks above are replaced by these variables safely
    const values = [name, category, description, tier, status, logo, address, phone];

    db.query(query, values, (err, results) => {
        if (err) {
            console.error('Error saving new business:', err.message);
            res.status(500).send('Error submitting your application.');
            return;
        }
        
        // If successful, redirect the user back to the homepage
        console.log("New business application received!");
        res.redirect('/');
    });
});

// 7. Start the server
app.listen(PORT, () => {
  console.log(`Server is running and listening on http://localhost:${PORT}`);
});
