@extends('layouts.app')

@section('title', 'User Registration')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <h2>User Registration</h2>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <form action="{{ route('users.register') }}" method="POST" enctype="multipart/form-data" id="userForm">
            @csrf
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" >
                @error('name')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label for="encoding" class="form-label">Image</label>
                <input type="file" name="encoding" id="encoding" class="form-control" accept="image/*" >
                @error('encoding')
                    <div class="text-danger mt-1">{{ $message }}</div>
                @enderror
            </div>



            <button type="submit" class="btn btn-primary">Register</button>
        </form>
    </div>
</div>

<script>
    // Simple frontend validation
    document.getElementById('userForm').addEventListener('submit', function(e){
        let name = document.getElementById('name').value.trim();
        let image = document.getElementById('image').files[0];

        if(name === '' || !image){
            alert('Please fill all required fields!');
            e.preventDefault();
            return false;
        }
    });
</script>
@endsection
