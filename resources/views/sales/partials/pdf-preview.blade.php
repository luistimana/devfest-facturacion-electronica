<div class="modal fade" id="ticketPreviewModal" tabindex="-1" aria-labelledby="ticketPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5" id="ticketPreviewTitle">Vista previa del ticket</h2><p class="small text-secondary mb-0">Formato térmico de 80 mm</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar vista previa"></button>
            </div>
            <div class="modal-body p-2 p-md-3"><iframe id="ticketPreviewFrame" class="w-100 border rounded bg-white" height="650" src="about:blank" title="Vista previa del comprobante PDF"></iframe></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(() => {
    const modal = document.querySelector('#ticketPreviewModal');
    const frame = document.querySelector('#ticketPreviewFrame');
    modal?.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        frame.src = button.dataset.pdfUrl;
        document.querySelector('#ticketPreviewTitle').textContent = `Ticket ${button.dataset.documentLabel}`;
        frame.title = `Ticket PDF ${button.dataset.documentLabel}`;
    });
    modal?.addEventListener('hidden.bs.modal', () => { frame.src = 'about:blank'; });
})();
</script>
@endpush
