@extends('layouts.master')
@section('title', 'Customer Details')
@section('content')

@include('partials.header')

<div class="content">
    
    <div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">

                	<div class="header">
                        <div class="row">
                            <div class="col-md-12">

	                            <div class="avtar-block">
	                            	<img src="{{$customer->profile_pic}}" class="img-shadow" width="200px" height="150px">
	                            	<div class="name-info">
	                            		<h3 class="title">Customer Name : {{$customer->name}}</h3>
	                            		@foreach($customer->addresses as $address)
	                                		<div class="address">{{$address->house_no.', '.$address->street_no.', '.$address->block.', '.$address->town.', '.$address->city}}</div>
	                                	@endforeach
	                                	<div class="phone">Email : {{$customer->email}}</div>
	                                	<div class="phone">Mobile : {{$customer->con_number}}</div>
	                                	<div class="phone">Current Loyality Points : 0</div>
	                                </div>
	                            </div>
								
                            </div>
                        </div>                                                
                    </div>
                    @if($customer->cars)
					<div class="content">
					    <div>
					        <div class="row">
					            <div class="col-md-12">
					            	<div class="table-responsive">
					            		<h4>Cars Details</h4>
										<table class="table table-striped dataTable table-bordered no-footer" role="grid" style="padding: 10px;">          	         
											<thead>
												<tr>
													<th class="text-center">ID </th>
													<th class="text-center">Car </th>
													<th class="text-center">Model </th>
													<th class="text-center">Vehicle No.</th>
													<th class="text-center"h>Millage</th>
													<th class="text-center">Insurance</th>
												</tr>
											</thead>
						                    <tbody>
						                    	@foreach($customer->cars as $key => $car)
						                        <tr> 
						                        	<td class="text-center">{{$key + 1}}</td>
						                        	<td class="text-center">{{ $car->make ." ". $car->model  }}</td>
						                        	<td class="text-center">{{ $car->pivot->year }}</td>
						                        	<td class="text-center">{{ $car->pivot->vehicle_no }}</td>
						                        	<td class="text-center">{{ $car->pivot->millage }}</td>
						                        	<td class="text-center">
						                        		@if($car->pivot->insurance == 1)
						                        			<i class="ti-check"></i>
				                                        @else
				                                        	<i class="ti-close"></i>
				                                        @endif
						                        	</td>
						                        </tr>        
						                        @endforeach 
						                    </tbody>
						                </table>
					                </div>
					            </div>
					        </div>

					    </div>
					</div>   
					@endif
					@if($customer->bookings)
					<div class="content">
					    <div>
					        <div class="row">
					            <div class="col-md-12">
					            	<div class="table-responsive">
					            		<h4>Bookings Details</h4>
										<table class="table table-striped dataTable table-bordered no-footer" role="grid" style="padding: 10px;">          	         
											<thead>
												<tr>
													<th class="text-center">ID</th>
													<th class="text-center">Workshop</th>
													<th class="text-center">Job Date Time</th>
													<th class="text-center">Status</th>
													<th class="text-center">Doorstep</th>
													<th class="text-center">Request at</th>
												</tr>
											</thead>
						                    <tbody>
						                    	@foreach($customer->bookings as $key => $booking)
						                        <tr> 
						                        	<td class="text-center">{{$key + 1}}</td>
						                        	<td class="text-center">{{$booking->workshop->name}}</td>
						                        	<td class="text-center">{{$booking->job_time." ".$booking->job_date }}</td>
						                        	<td class="text-center">{{$booking->job_status }}</td>
						                        	<td class="text-center">
						                        		@if($booking->is_doorstep == 1)
				                                        	<i class="ti-check"></i>
				                                        @endif
						                        	</td>
						                        	<td class="text-center">{{$booking->created_at->format('H:i D M, Y') }}</td>
						                        </tr>        
						                        @endforeach 
						                    </tbody>
						                </table>
					                </div>
					            </div>
					        </div>

					    </div>
					</div>   
					@endif
				</div>
			</div>
		</div>
	</div>
</div>
  
@include('partials.footer')
@endsection