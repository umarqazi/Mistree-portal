@extends('layouts.master')
@section('title', 'Active Bookings')
@section('content')
    @include('partials.header')

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="header">
                            <div class="row">
                                <div class="col-md-12">
                                    <h4 class="title">Bookings</h4>
                                    <p class="category">List of all Bookings.</p>
                                </div>
                                <div class="col-md-12">

                                    <div class="avtar-block">

                                        <div class="dropdown pull-right booking-types">
                                            <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                + More Options
                                            </button>
                                            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                                <a href="/admin/booking/active">Active Bookings</a>
                                                <a href="/admin/booking/pending">Pending Bookings</a>
                                                <a href="/admin/booking/cancelled">Rejected Bookings</a>
                                                <a href="/admin/booking/completed">Completed Bookings</a>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="content table-responsive table-full-width">
                            <div id="jsTable_wrapper" class="dataTables_wrapper no-footer">
                                <table class="table table-striped dataTable no-footer" id="jsTable" role="grid" aria-describedby="jsTable_info">
                                    <thead>
                                    <tr role="row">
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Date: activate to sort column ascending">Workshop ID</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Date: activate to sort column ascending">Workshop Name</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Date: activate to sort column ascending">Job Date</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Vehicle No.: activate to sort column ascending" >Vehicle No.</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Customer Name: activate to sort column ascending">Customer Name</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Customer Name: activate to sort column ascending">Status</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Services Booked: activate to sort column ascending">Services Booked</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Services Booked: activate to sort column ascending">Estiamted Rates</th>
                                        <th class="sorting text-center" tabindex="0" aria-controls="jsTable" rowspan="1" colspan="1" aria-label="Time: activate to sort column ascending">Job Time</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @if(count($bookings))
                                        @foreach($bookings as $booking)
                                            <tr role="row" class="odd">
                                                <td class="text-center">{{ $booking->workshop->workshopId}}</td>
                                                <td class="text-center">{{ $booking->workshop->name }}</td>
                                                <td class="text-center">{{\Carbon\Carbon::parse( $booking->job_date)->format('d M, Y')}}</td>
                                                <td class="text-center">{{$booking->vehicle_no}}</td>
                                                <td class="text-center">{{$booking->customer->name}}</td>
                                                <td class="text-center">{{$booking->job_status}}</td>
                                                <td class="text-center">{{@implode(', ', $booking->services->pluck('name')->toArray())}}</td>
                                                <td class="text-center">{{$booking->services->pluck('pivot')->pluck('service_rate')->sum()}} PKR</td>
                                                <td class="text-center">{{\Carbon\Carbon::parse($booking->job_time)->format('g:i A')}}</td>
                                            </tr>
                                        @endforeach
                                    @endif
                                    </tbody>
                                </table>

                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    @include('partials.footer')
@endsection
