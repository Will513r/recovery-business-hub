// 1. Import express and path
const express = require("express");
const path = require("path");

// 2. Initialize the application
const app = express();
const PORT = 3000;

// 3. Set EJS as our Template Engine
app.set("view engine", "ejs");
app.set("views", path.join(__dirname, "views")); // Tells Express where our .ejs files live

// 4. Serve static files (like our CSS) from the 'public' folder
app.use(express.static(path.join(__dirname, "public")));

// 5. Create some dummy data (simulating a database)
const businesses = [
    {
        id: 1,
        name: "Second Chance Cafe",
        category: "Food & Dining",
        description: "A cozy, recovery-owned coffee shop offering the best pastries in town.",
        isPremium: true,
        logo: "https://placehold.co/100x100/673AB7/FFF?text=SCC",
        address: "123 Main St, Richmond, IN 47374",
        phone: "(765) 555-0101"
    },
    {
        id: 2,
        name: "Sober Steps Landscaping",
        category: "Home Services",
        description: "Reliable and professional lawn care and landscaping services.",
        isPremium: false,
        logo: "https://placehold.co/100x100/4CAF50/FFF?text=SSL",
        address: "456 Oak Ln, Richmond, IN 47374",
        phone: "(765) 555-0202"
    },
    {
        id: 3,
        name: "New Beginnings Auto Repair",
        category: "Automotive",
        description: "Honest and affordable car maintenance and repair.",
        isPremium: true,
        logo: "https://placehold.co/100x100/FF9800/FFF?text=NBA",
        address: "789 Garage Blvd, Richmond, IN 47374",
        phone: "(765) 555-0303"
    }
];

// 6. Update our route to render the EJS file and pass it our data
app.get("/", (req, res) => {
  // This tells Express to load 'views/index.ejs' and send it the 'businesses' array
  res.render("index", { businesses: businesses });
});

// 7. Start the server
app.listen(PORT, () => {
  console.log(`Server is running and listening on http://localhost:${PORT}`);
});
