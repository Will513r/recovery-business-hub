<?php
// about.php
require_once 'config.php';
$page_title       = 'About';
$page_description = 'Recovery Business Hub lists businesses owned by people in recovery and jobs from employers who hire people in recovery or with a record. Cincinnati, Dayton and Northern Kentucky first.';
include 'header.php';
?>

<main class="about-page-wrapper">
    <h1>Businesses and jobs, built for people in recovery.</h1>

    <div class="prose">
        <p class="lead">
            Recovery Business Hub does two things. It lists businesses owned by people in recovery. And it lists jobs from employers who've said up front that they'll hire people in recovery or with a record.
        </p>

        <h2>Why both</h2>
        <p>
            Owners need workers. People getting back on their feet need work. Customers want somewhere good to spend their money. One site covers all three.
        </p>

        <h2>Where we're starting</h2>
        <p>
            Cincinnati, Dayton and Northern Kentucky. Anyone can list or post. We're just not chasing anyone outside those three areas yet.
        </p>

        <h2>Your story is yours</h2>
        <p>
            Business owners decide how much of their story to share. We show what an owner gives us and nothing more.
        </p>
        <p>
            Looking for work? You tell us nothing. No account, no record on file. Each job states the employer's hiring policy, and applying is an email to the employer. We get a copy, and we keep nothing on the site.
        </p>

        <h2>What it costs</h2>
        <p>
            Listings and job posts are free to start.
        </p>

        <h2>Who we are</h2>
        <p>
            We're not part of any recovery program or fellowship. Questions? Write to <a href="mailto:recoverybusinesshub@gmail.com">recoverybusinesshub@gmail.com</a>.
        </p>
    </div>

    <div class="about-footer">
        <a href="/jobs" class="btn-secondary">Find work</a>
        <a href="/businesses" class="btn-secondary">Find a business</a>
        <a href="/add-business" class="btn-secondary">List your business</a>
    </div>
</main>

<?php include 'footer.php'; ?>
