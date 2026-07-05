<footer style="background: var(--footer-bg); color: #94A3B8; padding: 3rem 2rem 1.5rem; margin-top: 4rem;">
    <div style="max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 2rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255,255,255,0.05);">

        <div>
            <h4 style="color: white; margin-bottom: 0.75rem; font-size: 1.1rem;">Recovery Business Hub</h4>
            <p style="font-size: 0.9rem; line-height: 1.7;">Connecting communities with businesses built by people in recovery. Every purchase fuels a comeback.</p>
        </div>

        <div>
            <h4 style="color: white; margin-bottom: 0.75rem; font-size: 1.1rem;">Quick Links</h4>
            <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem;">
                <li><a href="/" style="color: #ccc; text-decoration: none; font-size: 0.9rem;">🏠 Home / Directory</a></li>
                <li><a href="/about.php" style="color: #ccc; text-decoration: none; font-size: 0.9rem;">ℹ️ About Us</a></li>
                <li><a href="/add-business.php" style="color: #ccc; text-decoration: none; font-size: 0.9rem;">➕ Add Your Business</a></li>
            </ul>
        </div>

        <div>
            <h4 style="color: white; margin-bottom: 0.75rem; font-size: 1.1rem;">Contact</h4>
            <p style="font-size: 0.9rem; line-height: 1.7;">
                Questions or partnerships?<br>
                <a href="mailto:recoverybusinesshub@gmail.com" style="color: var(--accent-color); text-decoration: none;">recoverybusinesshub@gmail.com</a>
            </p>
        </div>

    </div>
    <p style="text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: #888;">
        &copy; <?php echo date('Y'); ?> Recovery Business Hub. All rights reserved.
    </p>
</footer>

<button id="backToTop" class="back-to-top" aria-label="Back to top">↑</button>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const backToTop = document.getElementById('backToTop');

        // Toggle visibility on scroll
        window.addEventListener('scroll', function() {
            if (window.scrollY > 300) {
                backToTop.classList.add('visible');
            } else {
                backToTop.classList.remove('visible');
            }
        });

        // Smooth scroll to top on click
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