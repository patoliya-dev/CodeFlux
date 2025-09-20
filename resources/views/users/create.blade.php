@extends('layouts.app')
@section('title', 'Face Attendance - User Registration')

@section('content')
<style>
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden !important; /* Remove page scrollbar */
        background: linear-gradient(135deg, #7f53ac, #647dee);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card {
        max-width: 400px;
        width: 100%;
        border-radius: 16px;
        box-shadow: 0 12px 24px rgba(72, 63, 160, 0.15);
        padding: 24px;
    }
    .form-label {
        font-weight: 600;
    }
    .invalid-feedback {
        font-size: 0.9rem;
    }
</style>

<div class="container-fluid d-flex justify-content-center align-items-center p-0">
    <div class="card shadow-sm">
        <div class="text-center mb-3">
            <!-- SVG icon to match navbar -->
            <svg style="width:40px; vertical-align:middle; margin-bottom:8px;" viewBox="0 0 24 24" fill="#764ba2">
                <circle cx="12" cy="8" r="4" opacity="0.5"/>
                <path d="M2,21c0-4,5-6,10-6s10,2,10,6" opacity="0.8"/>
            </svg>
            <h4>User Registration</h4>
            <p class="text-light small">Register your face for attendance</p>
        </div>

        @if(session('success'))
            <div class="alert alert-success mb-2">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger mb-2">{{ session('error') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <form action="{{ route('users.register') }}" method="POST" enctype="multipart/form-data" id="userForm">
            @csrf

            <div class="mb-3">
                <label for="name" class="form-label text-black">Name</label>
                <input type="text" name="name" id="name"
                       class="form-control @error('name') is-invalid @enderror"
                       placeholder="Enter your name" value="{{ old('name') }}">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="mb-3">
                <label for="image" class="form-label text-black">Profile Image</label>
                <input type="file" name="image" id="image"
                       class="form-control @error('image') is-invalid @enderror"
                       accept="image/*">
                @error('image')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3 text-center">
                <img id="imagePreview" src="#" alt="Image Preview" class="img-fluid rounded d-none" style="max-height:150px;">
            </div>

            <button type="submit" class="btn btn-primary w-100">Register</button>
        </form>
    </div>
</div>
<script>
    // Frontend validation & image preview
    document.getElementById('userForm').addEventListener('submit', function(e){
        let name = document.getElementById('name').value.trim();
        let image = document.getElementById('image').files[0];
        if(name === '' || !image){
            alert('Please fill all required fields!');
            e.preventDefault();
        }
    });

    document.getElementById('image').addEventListener('change', function(){
        let preview = document.getElementById('imagePreview');
        let file = this.files[0];
        if(file){
            let reader = new FileReader();
            reader.onload = function(e){
                preview.src = e.target.result;
                preview.classList.remove('d-none');
            }
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
        }
    });
</script>
@endsection
