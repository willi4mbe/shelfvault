@extends('admin.layout')

@section('title', __('admin.collection.create.page_title'))

@section('content')
    @include('admin.collection.form', [
        'mode' => 'create',
        'item' => $item,
        'action' => route('admin.collection.store'),
        'method' => 'POST',
        'backUrl' => $backUrl,
        'selectedType' => $selectedType,
        'typeOptions' => $typeOptions,
        'conditionOptions' => $conditionOptions,
        'formatOptions' => $formatOptions,
    ])
@endsection
