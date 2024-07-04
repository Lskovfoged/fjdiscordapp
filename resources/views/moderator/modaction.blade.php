@extends('layout.app')
@section('title', 'Flag Notices')
@section('content')
<div class="row">
@if($meta['showHeader'])
    <div class="col-md-12">
        <div class="col-md-3">
            <h2 class="text-center">{{$meta['count']}}</h2>
            <p class="text-center">Attributions</p>
            @if($meta['showRangePicker'] == true)
            He tried {{$meta['touchedContents']}} contents tho.
            @endif
        </div>
        <div class="col-md-9">

            <div class="dropdown">
                <button class="btn btn-default dropdown-toggle" type="button" id="userList" data-toggle="dropdown" aria-haspopup="true" aria-expanded="true">
                    {{$meta['user']}}
                    <span class="caret"></span>
                </button>
                <ul class="dropdown-menu" aria-labelledby="userList">
                    @foreach($meta["availableUsers"] as $user)
                        <li>
                        @if(isset($meta['lastTimeRated']))
                        <a href="{{route('moderator.ratings.viewuser', ['fjusername' => $user->fjuser->username, 'to' => null, 'from' => null ])}}">
                        @else
                        <a href="{{route('moderator.ratings.viewuser', ['fjusername' => $user->fjuser->username, 'to' => $meta['to'], 'from' => $meta['from'] ])}}">
                        @endif
                            {{$user->fjuser->username}}
                        </a>
                        </li>
                    @endforeach
                    <li role="separator" class="divider"></li>
                    <li><a href="{{route('moderator.ratings.nobody')}}">Pending Ratings</a></li>
                </ul>
            </div>
            <hr />
            @if($meta['showRangePicker'] == true)
                Date Range
                From: <input type="text" class="datetimepicker" name="range-from" id="range-from" value="{{$meta['from']->toDateTimeString()}}" />
                To: <input type="text" class="datetimepicker" name="range-to" id="range-to" value="{{$meta['to']->toDateTimeString()}}" /><br />
                <button class="btn btn-info" id="changeDateRange">View Chosen Range</button>
            @endif
            @if(isset($meta['lastTimeRated']))
                <p>User last rated at <strong>{{$meta['lastTimeRated']}}</strong><br />
                Showing <strong>{{$meta['from']}}</strong> to <strong>{{$meta['to']}}</strong> <br />
                This is a {{$meta['from']->diffInHours($meta['to'])}} hour span.</p>
                <a href="{{route('moderator.ratings.viewuser', ['fjusername' => $meta['fjusername'], 'to' => $meta['to'], 'from' => $meta['from']])}}">Direct Link To This Period</a>
                <br />
            @endif
        </div>
    </div>
@endif
    <div class="col-md-12">
        @foreach($contents as $content)
            <div class="panel panel-default">
                <div class="panel-heading">
                    @php
                        // Check if the URL is a full URL or a relative path
                        $url = (strpos($content->url, 'http') === 0) ? $content->url : 'https://funnyjunk.com' . $content->url;
                    @endphp
                    <a href="{{$url}}">{{$content->title}}</a> @ <a href="{{route('moderator.contentInfo', $content->id)}}">{{$content->id}}</a>
                </div>
                <div class="panel-body">
                    <span class="badge">PC {{$content->rating_pc}}</span>
                    <span class="badge">SKIN {{$content->rating_skin}}</span>
                    <span class="badge">{{$content->rating_category}}</span>
                    @if($content->flagged_as != null)
                        <div class="label label-danger content_flagged">FLAGGED {{$content->flagged_as}}</div>
                    @endif
                    @if($content->needsReview == true)
                        <div class="label label-warning content_needs_review">REVIEW NEEDED</div>
                    @endif
                    {{$content->created_at}}
                    <hr />
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($content->modaction as $action)
                                <tr @if($content->attributedTo != $action->user_id) class="warning" @endif>
                                    <td>{{$action->date}}</td>
                                    <td>{{$action->info}}</td>
                                    <td><a href="https://funnyjunk.com/u/{{$action->user->username ?? $action->user_id}}">{{$action->user->username ?? $action->user_id}}</a></td>
                                </tr>
                                 @foreach($action->notes as $note)
                                    <tr class="@if($note->category =='content_attribute' || $note->category == 'content_review')success
                                                @else danger
                                                @endif">
                                        <td></td>
                                        <td>{{$note->info}}</td>
                                        <td>{{$note->category}}</tD>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="panel-footer">
                    @if($content->attributedTo != null)
                        Attributed to: <a href="https://funnyjunk.com/u/{{$content->user->username}}">{{$content->user->username}}</a>
                    @else
                    Attribute To:
                        @foreach($content->modaction->pluck('user')->unique() as $user)
                            @if(isset($user->username))
                            <button type="button" class="btn btn-info btn-fill ratingAttributeContent" data-contentid="{{$content->id}}" data-userid="{{$user->fj_id}}">{{$user->username}}</button>
                            @endif
                        @endforeach
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection()
