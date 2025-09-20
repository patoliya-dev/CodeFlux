@extends('layouts.app')
@section('title', 'Attandance recognition')
@section('content')
<div class="row justify-content-center">
    <div class="col-md-12">
        <h2 class="text-light">Attandance recognition</h2>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        <form action="{{ route('attendance.recognize') }}" method="POST" enctype="multipart/form-data" id="attendanceForm">
            @csrf
            <div class="mb-3">
                <label for="image" class="form-label text-light">Image</label>
                <input type="file" name="image" id="image" class="form-control" accept="image/*" >
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
    document.getElementById('attendanceForm').addEventListener('submit', function(e){
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
