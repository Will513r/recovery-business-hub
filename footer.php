<footer>
    <div class="wrap">
        <div class="footer-top">
            <p class="footer-brand">recovery<br>business hub</p>
            <ul class="footer-links">
                <li><a href="/jobs">Jobs</a></li>
                <li><a href="/businesses">Businesses</a></li>
                <li><a href="/post-job">Post a job</a></li>
                <li><a href="/add-business">List your business</a></li>
                <li><a href="/manage-listing">Manage your listing</a></li>
                <li><a href="/about">About</a></li>
                <li><a href="mailto:recoverybusinesshub@gmail.com">recoverybusinesshub@gmail.com</a></li>
            </ul>
        </div>
        <hr class="footer-rule">
        <p class="footer-help">Need help right now? Call or text 988.</p>
        <p class="footer-credit">&copy; <?php echo date('Y'); ?> Recovery Business Hub.</p>
    </div>
</footer>

<button id="backToTop" class="back-to-top" aria-label="Back to top">&uarr;</button>

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
