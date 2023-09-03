@extends('layouts.app')
@section('content')
    <migrator :databases="{{ json_encode($databases) }}"></migrator>
@endsection
