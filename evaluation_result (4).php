<?php
/**
 * Reusable image uploader with client-side crop / zoom / move (Cropper.js).
 * The cropped result is exported as a fixed-size JPEG and submitted as a
 * base64 data-URL in a hidden field, then decoded with decode_cropped_image().
 * This keeps stored images small (avoids max_allowed_packet errors) and needs
 * no server image extensions.
 */

if (!function_exists('decode_cropped_image')) {
    /**
     * @return array{name:string,type:string,data:string}|null
     */
    function decode_cropped_image(?string $dataUrl, bool $required = false): ?array
    {
        $dataUrl = trim((string) $dataUrl);
        if ($dataUrl === '') {
            if ($required) {
                throw new RuntimeException('Please add a photo before saving.');
            }
            return null;
        }
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,#i', $dataUrl)) {
            throw new RuntimeException('The cropped image could not be read. Please re-select and try again.');
        }
        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1), true);
        if ($binary === false || strlen($binary) < 64) {
            throw new RuntimeException('The cropped image is invalid. Please try again.');
        }
        if (strlen($binary) > 5 * 1024 * 1024) {
            throw new RuntimeException('The image is too large. Please choose a smaller picture.');
        }
        $info = @getimagesizefromstring($binary);
        if ($info === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }
        return ['name' => 'photo.jpg', 'type' => 'image/jpeg', 'data' => $binary];
    }
}

if (!function_exists('photo_uploader_assets')) {
    function photo_uploader_assets(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<style>
.ih-up{ display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; }
.ih-up-frame{ background:var(--surface-inset,#f7f1f3); border:1px solid var(--line,#ebdfe3); border-radius:14px; overflow:hidden; position:relative; box-shadow:var(--shadow-sm,0 2px 8px rgba(42,8,16,.07)); cursor:pointer; flex:0 0 auto; transition:border-color .15s ease, box-shadow .15s ease; }
.ih-up-frame:hover{ border-color:var(--maroon-400,#c25b76); box-shadow:var(--shadow-md,0 8px 22px rgba(42,8,16,.09)); }
.ih-up-frame img{ width:100%; height:100%; object-fit:cover; display:block; }
.ih-up-empty{ position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; color:var(--ink-400,#9a868c); font-size:12.5px; text-align:center; padding:10px; }
.ih-up-empty i{ font-size:30px; color:var(--maroon-300,#db96a8); }
.ih-up-side{ flex:1; min-width:200px; }
.ih-up-hint{ font-size:12px; color:var(--ink-500,#786167); margin:10px 0 0; line-height:1.5; }
.ih-up-hint .badge{ background:var(--maroon-50,#fdf5f7); color:var(--maroon-700,#6e1228); border:1px solid var(--maroon-200,#efccd5); font-family:'IBM Plex Mono',monospace; font-weight:500; }
/* modal */
.ih-up-modal{ position:fixed; inset:0; z-index:2000; background:rgba(20,4,9,.6); display:none; align-items:center; justify-content:center; padding:20px; }
.ih-up-modal.show{ display:flex; }
.ih-up-dialog{ background:#fff; border-radius:16px; width:min(560px,100%); overflow:hidden; box-shadow:0 30px 70px rgba(20,4,9,.4); display:flex; flex-direction:column; }
.ih-up-head{ display:flex; justify-content:space-between; align-items:center; padding:14px 18px; border-bottom:1px solid var(--line,#ebdfe3); font-family:'Spectral',Georgia,serif; font-weight:700; font-size:1.05rem; color:var(--ink-950,#1c1417); }
.ih-up-x{ border:0; background:transparent; font-size:24px; line-height:1; cursor:pointer; color:var(--ink-500,#786167); }
.ih-up-stage{ height:56vh; max-height:440px; background:#2a0810; }
.ih-up-stage img{ max-width:100%; display:block; }
.ih-up-tools{ display:flex; align-items:center; gap:12px; padding:12px 18px; border-top:1px solid var(--line,#ebdfe3); }
.ih-up-tools input[type=range]{ flex:1; accent-color:var(--maroon-600,#8a1a38); }
.ih-up-tools button{ width:36px; height:36px; border-radius:9px; border:1px solid var(--line,#ebdfe3); background:#fff; cursor:pointer; font-size:15px; font-weight:700; color:var(--maroon-700,#6e1228); display:inline-flex; align-items:center; justify-content:center; }
.ih-up-tools button:hover{ background:var(--maroon-50,#fdf5f7); }
.ih-up-foot{ display:flex; justify-content:flex-end; gap:10px; padding:12px 18px; border-top:1px solid var(--line,#ebdfe3); }
.cropper-view-box, .cropper-face{ border-radius:0; }
</style>
        <?php
    }
}

if (!function_exists('photo_uploader_field')) {
    /**
     * @param array{name:string,aspect:string,outW:int,outH:int,current?:string,frameW?:int,ratioLabel?:string,help?:string} $o
     */
    function photo_uploader_field(array $o): void
    {
        $name   = $o['name'];
        $aspect = $o['aspect'];                 // e.g. "4 / 5"
        $outW   = (int) $o['outW'];
        $outH   = (int) $o['outH'];
        $current = $o['current'] ?? '';
        $frameW = (int) ($o['frameW'] ?? 210);
        $ratio  = $o['ratioLabel'] ?? str_replace(' ', '', $aspect);
        $help   = $o['help'] ?? 'JPG, PNG or WEBP. Drag to position, use the slider to zoom.';
        $hasCurrent = $current !== '';
        ?>
        <div class="ih-up" data-aspect="<?php echo htmlspecialchars($aspect); ?>" data-outw="<?php echo $outW; ?>" data-outh="<?php echo $outH; ?>">
            <div class="ih-up-frame" style="width:<?php echo $frameW; ?>px;aspect-ratio:<?php echo htmlspecialchars($aspect); ?>;">
                <img class="ih-up-preview" src="<?php echo htmlspecialchars($current); ?>" alt="Photo preview"<?php echo $hasCurrent ? '' : ' style="display:none"'; ?>>
                <div class="ih-up-empty"<?php echo $hasCurrent ? ' style="display:none"' : ''; ?>><i class="fas fa-image"></i><span>Click to add photo</span></div>
            </div>
            <div class="ih-up-side">
                <input type="file" class="ih-up-file" accept="image/jpeg,image/png,image/webp" hidden>
                <input type="hidden" name="<?php echo htmlspecialchars($name); ?>" class="ih-up-data">
                <button type="button" class="btn btn-primary btn-sm ih-up-pick"><i class="fas fa-arrow-up-from-bracket me-1"></i> <?php echo $hasCurrent ? 'Change photo' : 'Choose photo'; ?></button>
                <p class="ih-up-hint">Aspect ratio <span class="badge"><?php echo htmlspecialchars($ratio); ?></span><br><?php echo htmlspecialchars($help); ?></p>
            </div>
        </div>
        <?php
        photo_uploader_modal();
    }
}

if (!function_exists('photo_uploader_modal')) {
    function photo_uploader_modal(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        ?>
        <div class="ih-up-modal" id="ihUpModal" aria-hidden="true">
            <div class="ih-up-dialog" role="dialog" aria-modal="true">
                <div class="ih-up-head"><span>Adjust your photo</span><button type="button" class="ih-up-x" id="ihUpX" aria-label="Close">&times;</button></div>
                <div class="ih-up-stage"><img id="ihUpCropImg" alt="Crop area"></div>
                <div class="ih-up-tools">
                    <button type="button" id="ihUpZoomOut" title="Zoom out">&minus;</button>
                    <input type="range" id="ihUpZoom" min="0" max="1" step="0.01" value="0" aria-label="Zoom">
                    <button type="button" id="ihUpZoomIn" title="Zoom in">+</button>
                    <button type="button" id="ihUpRotate" title="Rotate"><i class="fas fa-rotate-right"></i></button>
                </div>
                <div class="ih-up-foot">
                    <button type="button" class="btn btn-outline-secondary" id="ihUpCancel">Cancel</button>
                    <button type="button" class="btn btn-primary" id="ihUpApply"><i class="fas fa-check me-1"></i> Apply</button>
                </div>
            </div>
        </div>
        <script>
        (function(){
            var modal, cropImg, zoom, cropper=null, activeUp=null, minZoom=0, maxZoom=0;
            function q(s){ return document.querySelector(s); }
            function openModal(file, up){
                activeUp = up;
                cropImg.src = URL.createObjectURL(file);
                modal.classList.add('show'); modal.setAttribute('aria-hidden','false');
                if (cropper){ cropper.destroy(); cropper=null; }
                var p = up.dataset.aspect.split('/');
                var ar = parseFloat(p[0]) / parseFloat(p[1]);
                cropper = new Cropper(cropImg, {
                    aspectRatio: ar, viewMode: 1, dragMode: 'move', autoCropArea: 1,
                    background: false, guides: false, center: true, cropBoxMovable: false,
                    cropBoxResizable: false, toggleDragModeOnDblclick: false, restore: false,
                    ready: function(){
                        var d = cropper.getImageData();
                        minZoom = d.width / d.naturalWidth; maxZoom = minZoom * 4;
                        zoom.value = 0;
                    }
                });
            }
            function closeModal(){
                modal.classList.remove('show'); modal.setAttribute('aria-hidden','true');
                if (cropper){ cropper.destroy(); cropper=null; }
                activeUp = null;
            }
            function applyCrop(){
                if (!cropper || !activeUp){ closeModal(); return; }
                var canvas = cropper.getCroppedCanvas({
                    width: +activeUp.dataset.outw, height: +activeUp.dataset.outh,
                    imageSmoothingEnabled: true, imageSmoothingQuality: 'high', fillColor: '#ffffff'
                });
                if (!canvas){ closeModal(); return; }
                var data = canvas.toDataURL('image/jpeg', 0.9);
                activeUp.querySelector('.ih-up-data').value = data;
                var prev = activeUp.querySelector('.ih-up-preview');
                var empty = activeUp.querySelector('.ih-up-empty');
                prev.src = data; prev.style.display = 'block';
                if (empty) empty.style.display = 'none';
                var btn = activeUp.querySelector('.ih-up-pick');
                if (btn) btn.innerHTML = '<i class="fas fa-arrow-up-from-bracket me-1"></i> Change photo';
                closeModal();
            }
            document.addEventListener('DOMContentLoaded', function(){
                modal = q('#ihUpModal'); cropImg = q('#ihUpCropImg'); zoom = q('#ihUpZoom');
                document.addEventListener('click', function(e){
                    var pick = e.target.closest('.ih-up-pick');
                    var frame = e.target.closest('.ih-up-frame');
                    var trigger = pick || frame;
                    if (trigger){
                        var up = trigger.closest('.ih-up');
                        if (up){ e.preventDefault(); up.querySelector('.ih-up-file').click(); }
                    }
                });
                document.addEventListener('change', function(e){
                    if (e.target.classList && e.target.classList.contains('ih-up-file')){
                        var f = e.target.files && e.target.files[0];
                        if (f){ openModal(f, e.target.closest('.ih-up')); }
                        e.target.value = '';
                    }
                });
                q('#ihUpCancel').addEventListener('click', closeModal);
                q('#ihUpX').addEventListener('click', closeModal);
                q('#ihUpApply').addEventListener('click', applyCrop);
                q('#ihUpZoomIn').addEventListener('click', function(){ if (cropper) cropper.zoom(0.1); });
                q('#ihUpZoomOut').addEventListener('click', function(){ if (cropper) cropper.zoom(-0.1); });
                q('#ihUpRotate').addEventListener('click', function(){ if (cropper) cropper.rotate(90); });
                zoom.addEventListener('input', function(){ if (cropper) cropper.zoomTo(minZoom + (maxZoom - minZoom) * parseFloat(zoom.value)); });
                modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
            });
        })();
        </script>
        <?php
    }
}
