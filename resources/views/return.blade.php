@extends('layouts.app')

@section('title', __('Charge Return'))

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-12">
                @if($charge)
                    支付成功！
                @endif
            </div>
        </div>
    </div>
@endsection