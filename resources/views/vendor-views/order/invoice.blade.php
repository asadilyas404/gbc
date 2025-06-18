@extends('layouts.vendor.app')

@section('title', translate('messages.Order_Invoice'))

@section('content')
    {{-- @include('new_invoice') --}}
    @include('order_receipt')
@endsection
