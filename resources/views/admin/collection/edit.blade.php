@extends('admin.layout')

@section('title', __('admin.collection.edit.page_title'))

@section('content')
    @include('admin.collection.form', [
        'mode' => 'edit',
        'item' => $item,
        'action' => route('admin.collection.update', $item),
        'method' => 'PUT',
        'backUrl' => $backUrl,
        'selectedType' => $selectedType,
        'typeOptions' => $typeOptions,
        'conditionOptions' => $conditionOptions,
        'formatOptions' => $formatOptions,
    ])
@endsection
