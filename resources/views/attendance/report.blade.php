@extends('layouts.app')
@section('title', 'Attendance Recognition')
@section('content')
<style>
    /* Better look for multi-select */
    .custom-multiselect {
        height: auto; /* expand automatically */
        min-height: 120px;
        padding: 6px;
        border-radius: 8px;
    }

    /* Style options */
    .custom-multiselect option {
        padding: 8px;
        margin: 2px 0;
        border-radius: 5px;
    }

    /* Highlight selected options */
    .custom-multiselect option:checked {
        background-color: #0d6efd;
        color: #fff;
        font-weight: 500;
    }
</style>
<div class="container py-4">
    <h2 class="mb-4 text-light">Attendance Recognition</h2>
    <div class="mb-4">
        <form method="POST" action="{{ route('attendance.report') }}" id="filterForm" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label for="user_id" class="form-label text-light">User</label>
                <select name="user_id[]" id="user_id" class="form-select custom-multiselect" multiple>
                    <option value="">All Users</option>
                    @foreach($users as $user)
                        <option value="{{ $user['id'] }}" {{ in_array($user['id'], (!empty($userIds) ? $userIds : [])) ? 'selected' : '' }}>
                            {{ $user['name'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="start_date" class="form-label text-light">Start Date</label>
                <input type="date" name="start_date" id="start_date" value="{{ $startDate }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label text-light">End Date</label>
                <input type="date" name="end_date" id="end_date" value="{{ $endDate }}" class="form-control">
            </div>

            <div class="col-auto">
                <input type="hidden" name="export" id="export" value="filter">
                <button type="submit" class="btn btn-primary" onclick="setExportType('filter')">Filter</button>
                <button type="submit" class="btn btn-primary" onclick="setExportType('csv')">Export CSV</button>
                <button type="submit" class="btn btn-primary" onclick="setExportType('pdf')">Export PDF</button>
            </div>
        </form>
    </div>

    @foreach($reportData as $userId => $user)
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">{{ $user['name'] }}</h3>
            </div>
            <div class="card-body">
                @foreach($user['dates'] as $date => $details)
                    <div class="mb-3">
                        <h5>
                            Date: <span class="text-secondary">{{ \Carbon\Carbon::parse($date)->format('F d, Y') }}</span>
                            <span class="badge bg-info text-dark ms-3">Total Hours: {{ $details['total_hours'] }}</span>
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($details['entries'] as $entry)
                                        <tr>
                                            <td>
                                                @php
                                                    $status = strtolower($entry['status']);
                                                    $badgeClass = match($status) {
                                                        'present' => 'bg-success',
                                                        'absent' => 'bg-danger',
                                                        'late' => 'bg-warning text-dark',
                                                        default => 'bg-secondary',
                                                    };
                                                @endphp
                                                <span class="badge {{ $badgeClass }}">
                                                    {{ ucfirst($entry['status']) }}
                                                </span>
                                            </td>
                                            <td>{{ $entry['time'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<script>
document.getElementById('filterForm').addEventListener('submit', function (e) {
    const startDate = document.getElementById('start_date').value;
    const endDate   = document.getElementById('end_date').value;

    if (startDate && !endDate) {
        e.preventDefault();
        alert('End Date is required if Start Date is selected.');
        document.getElementById('end_date').focus();
    }

    if (endDate && !startDate) {
        e.preventDefault();
        alert('Start Date is required if End Date is selected.');
        document.getElementById('start_date').focus();
    }
});
function setExportType(type) {
    document.getElementById('export').value = type;
}
</script>
@endsection
