<?php
// footer.php
?>
</main> 
</div> <footer class="footer mt-auto py-3 bg-light border-top d-print-none">
    <div class="container text-center">
        <span class="text-muted small">
            &copy; <?php echo date('Y'); ?> ระบบยืมคืนอุปกรณ์ผู้ป่วย | 
            พัฒนาโดย: นายจิรายุสม์ ทองเฟื่อง นักวิชาการคอมพิวเตอร์ | 
            กลุ่มงานสุขภาพดิจิทัล โรงพยาบาลทรายมูล
        </span>
    </div>
</footer>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

<div class="modal fade" id="imagePreviewModal" tabindex="-1" role="dialog" aria-hidden="true" style="z-index: 9999;">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background: none; border: none;">
            <div class="modal-body p-0 text-center position-relative">
                <button type="button" class="btn btn-danger btn-circle shadow-lg" data-dismiss="modal" 
                        style="position: absolute; right: -15px; top: -15px; z-index: 10001; width: 40px; height: 40px; border-radius: 50%;">
                    <i class="fas fa-times"></i>
                </button>
                <img src="" id="displayFullImage" class="img-fluid rounded shadow-lg" 
                     style="max-height: 90vh; border: 5px solid white; background-color: white; opacity: 1 !important;">
            </div>
        </div>
    </div>
</div>

<style>
    /* บังคับพื้นหลัง Modal ให้มืดสนิทเพื่อให้รูปชัด */
    .modal-backdrop.show { opacity: 0.85 !important; }
    .btn-circle { width: 30px; height: 30px; padding: 6px 0; border-radius: 15px; text-align: center; font-size: 12px; line-height: 1.42857; }
</style>

<script>
$(document).ready(function() {
    // ฟังก์ชันดูรูปภาพแบบ Global (ใช้ได้ทั้งหน้า History, Outstanding และ Loan Form)
    $(document).on('click', '.open-modal-trigger, .btn-view-photo, .img-evidence-thumb', function(e) {
        e.preventDefault();
        var imgUrl = $(this).attr('data-img') || $(this).attr('src');
        
        if(imgUrl) {
            $('#displayFullImage').css('opacity', '0').attr('src', imgUrl);
            $('#imagePreviewModal').modal('show');
            
            // ทำให้รูปค่อยๆ แสดงผลเมื่อโหลดเสร็จเพื่อความคมชัด
            $('#displayFullImage').on('load', function() {
                $(this).animate({opacity: 1}, 200);
            });
        }
    });

    // ล้างค่ารูปภาพเมื่อปิด Modal
    $('#imagePreviewModal').on('hidden.bs.modal', function () {
        $('#displayFullImage').attr('src', '');
    });

    // จัดการรูปเสีย
    $('img').on('error', function() {
        $(this).attr('src', 'assets/img/no-image.png').css('opacity', '0.5');
    });
});
</script>

</body>
</html>