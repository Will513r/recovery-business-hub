<footer>
    <div class="footer-grid">

        <div>
            <h4>Recovery Business Hub</h4>
            <p>Connecting communities with businesses built by people in recovery. Every purchase fuels a comeback.</p>
        </div>

        <div>
            <h4>Quick Links</h4>
            <ul>
                <li><a href="/">Home / Directory</a></li>
                <li><a href="/about.php">About Us</a></li>
                <li><a href="/pricing.php">Pricing</a></li>
                <li><a href="/add-business.php">Add Your Business</a></li>
            </ul>
        </div>

        <div>
            <h4>Contact</h4>
            <p>
                Questions or partnerships?<br>
                <a href="mailto:recoverybusinesshub@gmail.com">recoverybusinesshub@gmail.com</a>
            </p>
        </div>

    </div>
    <p class="footer-copy">
        &copy; <?php echo date('Y'); ?> Recovery Business Hub. All rights reserved.
    </p>
</footer>

<button id="backToTop" class="back-to-top" aria-label="Back to top">↑</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const backToTop = document.getElementById('backToTop');

        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        backToTop.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    });
</script>

</body>

</html>
