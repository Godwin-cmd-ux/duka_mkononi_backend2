{{-- Cloudinary config for pages whose main script is wrapped in @verbatim.
    This partial MUST be included outside any @verbatim block (e.g. in the
    gap next to @include('partials.photo-viewer')) so that {{ }} is actually
    interpolated by Blade. The page scripts then read window.CLOUDINARY_CONFIG. --}}
<script>
    window.CLOUDINARY_CONFIG = {
        cloudName: '{{ config('cloudinary.cloud_name') }}',
        uploadPreset: '{{ config('cloudinary.upload_preset') }}'
    };
</script>
