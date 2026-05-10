@extends('layouts.app')

@section('content')
<div class="container" style="max-width:600px;">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold" style="color:#244A86;">QR Legajo Técnico</h2>
        <img src="{{ asset('img/logo.png') }}" alt="Cerámica Quilmes" style="height:100px;">
    </div>

    <div class="card shadow-sm p-4 mb-4" style="background:#f8f9fa;">
        <div class="mb-3">
            <label for="proveedor" class="form-label fw-semibold">Proveedor</label>
            <select id="proveedor" class="form-select border-primary">
                <option value="">Cargando...</option>
            </select>
        </div>
        <button id="btn-generar" class="btn w-100 text-white" style="background:#244A86;" disabled>
            Generar QR
        </button>
    </div>

    <div id="resultado" class="card shadow-sm p-4 text-center" style="display:none;">
        <p class="text-muted mb-2" id="qr-label"></p>
        <canvas id="qr-canvas" class="mx-auto d-block"></canvas>
        <a id="btn-descargar" class="btn btn-outline-secondary mt-3">
            ⬇ Descargar QR (PNG)
        </a>
    </div>

    <div id="mensaje-error" class="alert alert-danger mt-3" style="display:none;"></div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('js/qrious.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', async function () {
    const selProv    = document.getElementById('proveedor');
    const btnGenerar = document.getElementById('btn-generar');
    const resultado  = document.getElementById('resultado');
    const qrCanvas   = document.getElementById('qr-canvas');
    const qrLabel    = document.getElementById('qr-label');
    const btnDesc    = document.getElementById('btn-descargar');
    const errBox     = document.getElementById('mensaje-error');

    // Cargar proveedores
    try {
        const res  = await fetch('{{ route("legajo.proveedores") }}');
        const data = await res.json();
        selProv.innerHTML = '<option value="">-- Seleccioná un proveedor --</option>';
        data.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p;
            opt.textContent = p;
            selProv.appendChild(opt);
        });
        btnGenerar.disabled = false;
    } catch (e) {
        selProv.innerHTML = '<option value="">Error al cargar proveedores</option>';
    }

    // Generar QR
    btnGenerar.addEventListener('click', async function () {
        errBox.style.display    = 'none';
        resultado.style.display = 'none';

        const proveedor = selProv.value;
        if (!proveedor) {
            errBox.textContent   = 'Seleccioná un proveedor.';
            errBox.style.display = 'block';
            return;
        }

        btnGenerar.disabled     = true;
        btnGenerar.textContent  = 'Generando...';

        try {
            const res  = await fetch(
                '{{ route("legajo.generar-qr") }}?proveedor=' + encodeURIComponent(proveedor)
            );
            const data = await res.json();

            if (!res.ok || data.error) {
                throw new Error(data.error || 'Error desconocido');
            }

            const qr = new QRious({
                element:         qrCanvas,
                value:           data.url,
                size:            300,
                backgroundAlpha: 1,
            });

            qrLabel.textContent = proveedor + ' — Legajo técnico';

            const provSlug   = proveedor.replace(/[^a-z0-9]/gi, '_').toLowerCase();
            btnDesc.href     = qr.toDataURL('image/png');
            btnDesc.download = 'legajo_tecnico_' + provSlug + '.png';

            resultado.style.display = 'block';
        } catch (e) {
            errBox.textContent   = 'Error: ' + e.message;
            errBox.style.display = 'block';
        } finally {
            btnGenerar.disabled    = false;
            btnGenerar.textContent = 'Generar QR';
        }
    });
});
</script>
@endpush
