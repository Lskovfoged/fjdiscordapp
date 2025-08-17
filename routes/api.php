<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//For FJ Admin, lets make it easy for him
Route::middleware('throttle:1000,1,1')->post('/fjuser/{fjid}', function (Request $request, $fjid) {
    if($request->input('key') != env('FJ_ADMIN_KEY'))
        abort(403);
    $user = \App\FunnyjunkUser::where('fj_id', $fjid)->orderBy('created_at', 'desc')->firstOrFail();
    logger("API Request for", ["fj_username", $user->username]);
    return $user->user;
});


//For everyone else, fuck you we do it properly here
Route::group(['middleware' => 'throttle:60,1,1'], function(){

	//FJUser
    Route::get('/fjuser/basicUserByName/{username}', 'API\FJUserController@getBasicUserByUsername')->middleware(['auth:api', 'scope:fjapi-userinfo-basic', 'role:mod.isAMod']);
    Route::get('/fjuser/modUserByName/{username}', 'API\FJUserController@getModUserByUsername')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
   
	Route::get('/fjuser/discordByID/{fjid}', function($fjid){
		if(Auth::user()->cannot('mod.isAMod'))
					abort(403);
			$user = \App\FunnyjunkUser::where('fj_id', $fjid)->with('user')->get()->pluck('user');
			return $user;
	})->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
	
    Route::get('/fjuser/history/{fjusername}/{fjid}', function($fjusername, $fjid){
		if(Auth::user()->cannot('mod.isAMod'))
					abort(403);
			$user = \App\ModAction::where('owner', $fjusername)
            ->whereIn('category', array('flag', 'unflag', 'comment_flag', 'comment_unflag', 'cover_flag', 'cover_unflag', 'ban', 'avatar_flag', 'spam_comment_flag', 'voteban'))
            ->get();
            $previousGays = \App\ModAction::where('reference_type', 'user')->where('reference_id', $fjid)->get();
            $user = $user->merge($previousGays);
			return $user;
    })->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
	
	Route::get('/fjuser/ban/{fjid}', function($fjid){
		if(Auth::user()->cannot('mod.permabanuser'))
			abort(403);
		$fj = new Posttwo\FunnyJunk\FunnyJunk();
		$fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
		$user = new Posttwo\FunnyJunk\User();
		$user->set(array('id' => $fjid));
		$user->permaBan(Auth::user()->fjuser->fj_id);
		logger()->info("User Permabanned", ["moderator" => Auth::user(), "user" => $user]);
	})->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
    
	//FJMeme by Username
	Route::get('/fjmeme/getUserFJMemeInfoByFJUsername/{username}', 'API\FJUserController@getUserFJMemeInfoByFJUsername')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/revokeModeratorPermissionByFJUsername/{username}', 'API\FJUserController@revokeModeratorPermissionByFJUsername')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/giveUserAccessToOAuthByFJUsername/{username}', 'API\FJUserController@giveUserAccessToOAuthByFJUsername')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/revokeUserAccessToOAuthByFJUsername/{username}', 'API\FJUserController@revokeUserAccessToOAuthByFJUsername')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/nukeFJMemeUserByFJUsername/{username}', 'API\FJUserController@nukeFJMemeUserByFJUsername')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);

	//FJMeme by ID
	Route::get('/fjmeme/getUserFJMemeInfoByID/{id}', 'API\FJUserController@getUserFJMemeInfoByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/revokeModeratorPermissionByID/{id}', 'API\FJUserController@revokeModeratorPermissionByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);

	Route::get('/fjmeme/giveUserAccessToOAuthByID/{id}', 'API\FJUserController@giveUserAccessToOAuthByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/revokeUserAccessToOAuthByID/{id}', 'API\FJUserController@revokeUserAccessToOAuthByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/giveUserAccessToReviewByID/{id}', 'API\FJUserController@giveUserAccessToReviewByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);
	Route::get('/fjmeme/nukeFJMemeUserByID/{id}', 'API\FJUserController@nukeFJMemeUserByID')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.isExec']);

    Route::post('/mods/discord/help', 'API\DiscordHelpController@sendHelpRequest')->middleware(['auth:api', 'scope:discord-post-modhelp', 'role:mod.isAMod']);
    Route::get('/mods/notetoken', 'ModeratorController@getOrCreateNotesToken')->middleware(['auth:api', 'role:mod.isAMod', 'scope:fjmod-token']);

	//User Flags

	Route::get('/mods/userflags/getUnpatrolled', 'UserFlagController@getCount')->middleware(['auth:api', 'role:mod.isAMod', 'scope:fjmod-token']);
	Route::get('/mods/userflags/getByUserId/{id}', 'UserFlagController@getByUserId')->middleware(['auth:api', 'role:mod.isAMod', 'scope:fjmod-token']);
	//Content Review

	Route::get('/ratings/{fjusername}', 'ModActionController@getNextContentNeedingReview')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.ratingReviewer']);
	Route::get('/ratings/removeNeedsReview/{fjcontent}', 'ModActionController@removeNeedsReview')->middleware(['auth:api', 'scope:fjmeme-change-user', 'role:mod.ratingReviewer']);
		
	//Flag Notices
	Route::post('/mods/flagNotice', 'API\FlagNoticeController@getFlagNotices')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
	Route::post('/mods/addFlagNotice', 'API\FlagNoticeController@addFlagNotice')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isExec']);
	Route::delete('/mods/flagNotice/{flagNotice}', 'API\FlagNoticeController@deleteFlagNotice')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isExec']);

	//User Notes
	Route::get('/mods/userNotes/{fjUserId}', 'API\FJUserNoteController@getUserNotes')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
	Route::get('/mods/userNotes/{fjUserId}/{username}/import', 'API\FJUserNoteController@importLegacyNotes')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isExec']);
	Route::post('/mods/userNotes/{fjUserId}', 'API\FJUserNoteController@addUserNote')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);
	Route::post('/mods/userNote/{noteId}/highlight', 'API\FJUserNoteController@setUserNoteHighlight')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isExec']);
	Route::delete('/mods/userNote/{noteId}', 'API\FJUserNoteController@removeUserNote')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isExec']);

	//Mod Complaints
	Route::post('/mods/newcomplaint/', 'ModCaseController@createOutboundCase')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);

	//Mod Info
	Route::get('/mods/modinfo', 'API\ModInfoController@getModInfo')->middleware(['auth:api', 'scope:fjapi-userinfo-mod', 'role:mod.isAMod']);

	//Discord <=> FunnyJunk resolve
	Route::get('/fjmeme/discordToFunnyJunk/{discordId}', function(Request $request, $discordId){

		$user = \App\User::where('discord_id', $discordId)->with('fjuser')->first();
        return $user;

	})->middleware(['auth:api', 'scope:fjapi-userinfo-basic']);

	Route::get('/fjmeme/funnyJunkToDiscord/{fjname}', function(Request $request, $fjname){

		$user = \App\FunnyjunkUser::where('username', $fjname)->with('user')->first();
		return $user;

	})->middleware(['auth:api', 'scope:fjapi-userinfo-basic']);

});

Route::get('/user/', function(Request $request){
	if(Auth::user()->cannot('mod.isAMod') && Auth::user()->cannot('user.canUseFJMemeForSingleSignOn'))
                abort(403);
	$avatar = $request->user()->avatar;
	try{
		if(get_headers($avatar, 1)[0] == 'HTTP/1.1 404 Not Found')
			$avatar = 'https://new2.fjcdn.com/site/funnyjunk/images/def_avatar.gif';
	}catch(Exception $e){
		$avatar = 'https://new2.fjcdn.com/site/funnyjunk/images/def_avatar.gif';
		logger()->error("Error in get_headers", ["user" => $request->user()]);
	}
	//$return['user'] = $request->user();
	$return['id'] = $request->user()->id;
	$return['name'] = $request->user()->fjuser->username;
	$return['nickname'] = $request->user()->fjuser->username;
	$return['avatar'] = $avatar;
	$return['email'] = $request->user()->fjuser->username . '@users.fjme.me';
	$return['fjuser'] = $request->user()->fjuser;
	$return['roles'] = $request->user()->permissions;
	return $return;
})->middleware(['auth:api', 'scope:fjapi-userinfo-basic']);
