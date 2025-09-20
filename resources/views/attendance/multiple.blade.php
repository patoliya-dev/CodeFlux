@extends('layouts.app')
@section('title', 'Attandance recognition')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        <h2 class="text-light">Multiple Attandance recognition</h2>
        @if(session('success'))
            <div class="alert alert-success">{!! session('success') !!}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <form action="{{ route('attendance.multiple-recognize') }}" method="POST" enctype="multipart/form-data" id="multipleRecognizeForm">
            @csrf
            <div class="mb-3">
                <label for="image" class="form-label text-light">Image</label>
                <input type="file" name="images[]" id="images" class="form-control" accept="image/*" multiple>
                @error('image')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary">Recognize</button>
        </form>
    </div>
</div>
<script>
    // Simple frontend validation
    document.getElementById('multipleRecognizeForm').addEventListener('submit', function(e){
        const imagesInput = document.getElementById('images');
        const files = imagesInput.files;

        if(!files || files.length === 0){
            alert('Please select at least one image to recognize!');
            e.preventDefault();
            return false;
        }

        // Optional: validate file types
        for(let i=0; i<files.length; i++){
            if(!files[i].type.startsWith('image/')){
                alert('Only image files are allowed!');
                e.preventDefault();
                return false;
            }
        }

        // Optional: limit max number of images
        if(files.length > 10){
            alert('You can upload maximum 10 images at a time.');
            e.preventDefault();
            return false;
        }
    });
</script>
@endsection
