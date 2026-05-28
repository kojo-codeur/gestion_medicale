<?php
// includes/footer.php
?>

            </main>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3"></div>
    
    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/main.js"></script>
    
    <?php if (isset($customScripts)) echo $customScripts; ?>
    
    <script>
        // Initialiser les composants Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
        });
    </script>
    
    <footer class="footer mt-5 py-3 bg-light border-top">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> Gestion Médicale. Tous droits réservés.
                    </span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">
                        Version 1.0.0 | 
                        <?php echo isLoggedIn() ? 'Connecté en tant que ' . $_SESSION['role'] : 'Non connecté'; ?>
                    </span>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>