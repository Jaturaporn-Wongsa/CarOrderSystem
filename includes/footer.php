<?php
/**
 * ==============================================================================
 * File: includes/footer.php
 * Description: Master Footer & Global UI Scripts
 * ==============================================================================
 */
?>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Global System Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 1. ระบบปิดการแจ้งเตือนอัตโนมัติ (ภายใน 5 วินาที)
            setTimeout(function() {
                const alertList = document.querySelectorAll('.alert');
                alertList.forEach(function(alert) {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                        if (bsAlert) {
                            bsAlert.close();
                        }
                    }
                });
            }, 5000);

            // 2. ระบบจัดเรียงลำดับตารางออเดอร์อัตโนมัติ (1, 2, 3 จากบนลงล่าง)
            const tableBody = document.querySelector("table tbody");
            if (tableBody) {
                const rows = Array.from(tableBody.querySelectorAll("tr"));
                if (rows.length > 1) {
                    rows.sort((a, b) => {
                        const idA = parseInt(a.querySelector('td')?.innerText.replace(/[^0-9]/g, '') || 0, 10);
                        const idB = parseInt(b.querySelector('td')?.innerText.replace(/[^0-9]/g, '') || 0, 10);
                        return idA - idB;
                    });
                    
                    tableBody.innerHTML = "";
                    rows.forEach(row => tableBody.appendChild(row));
                }
            }
        });
    </script>
</body>
</html>