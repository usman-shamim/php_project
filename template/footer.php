<?php
// templates/footer.php
// Closes the main content div, container div, and body tags.

// REMOVED: if (isset($conn) && $conn instanceof mysqli && $conn->ping()) { $conn->close(); }

// This is still good practice to ensure the connection is closed, but we'll do it
// in the main script now. 
// If your script has a logic path that redirects or exits early, it might not be closed.
?>
        </div> <hr class="mt-4">
        <footer class="text-center text-muted">
            <p>&copy; <?php echo date("Y"); ?> Salon Management System | Logged in as: <?php echo htmlspecialchars($_SESSION['role'] ?? 'Guest'); ?></p>
        </footer>
    </div> <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>