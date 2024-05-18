<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Storage;
use DB;
use Auth;
use Carbon\Carbon;
use App\ModAction;
use App\ModActionNote;
use App\FJContent;
use App\FunnyjunkUser;
use App\Slack;
use App\User;
use App\Exceptions\ModActionParseErrorException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Posttwo\FunnyJunk\FunnyJunk;
use App\UserFlagPatrol;
Use Exception;

class ModActionController extends Controller
{

    public function getContentById(FJContent $fjcontent)
    {
        $contents[] = $fjcontent;
        $meta['showHeader'] = false; //@TODO IMPLEMENT
        $meta['showRangePicker'] = false;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);
    }
    
    public function getContentAttributedToUser($fjusername, $from = null, $to = null)
    {
        if($fjusername == "self")
        {
            $fjusername = Auth::user()->fjuser->username;
        }
        $fjuser = FunnyjunkUser::where('username', $fjusername)->firstOrFail();
        if($from == null && $to == null)
        {
            $lastTimeRated = $this->getLastTimeUserRatedContent($fjuser);
            $meta['lastTimeRated'] = $lastTimeRated->copy();
            $to = $lastTimeRated->copy()->addHour();
            $from = $lastTimeRated->copy()->subDay()->subHour();
        }
        else
        {
            $from = Carbon::parse($from);
            $to = Carbon::parse($to);
            //dd($from, $to);
        }
        //Get available users
        $availableUser = User::permission('mod.isAMod')->with('fjuser')->get();
        $contents = FJContent::with('modaction')
                    ->with('modaction.notes')
                    ->with('user')
                    ->with('modaction.user')
                    ->where('attributedTo', $fjuser->fj_id)
                    ->whereBetween('created_at', [$from, $to])
                    ->orderBy('id', 'desc')
                    ->get();
        
        //How much did he try tho?
        $actions = ModAction::distinct('reference_id')->where('user_id', $fjuser->fj_id)
                 ->whereBetween('date', [$from, $to])
                 ->where('reference_type', 'content')
                 ->get(['reference_id'])
                 ->count();

        $meta['fjusername'] = $fjusername;
        $meta['showHeader'] = true;
        $meta['touchedContents'] = $actions;
        $meta['from'] = $from ?? "NO RANGE";
        $meta['to'] = $to ?? "SHOWING 24";
        $meta['user'] = $fjuser->username;
        $meta['count'] = $contents->count();
        $meta['availableUsers'] = $availableUser;
        $meta['showRangePicker'] = true;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);
    }

    protected function getLastTimeUserRatedContent(FunnyjunkUser $user)
    {
        $action = $user->modaction()->orderBy('id', 'desc')->whereIn('category', ['category', 'pc_level', 'skin_level'])->firstOrFail();
        return $action->date;
    }

    public function getContentWithNoAttribution()
    {
        $contents = FJContent::with('modaction')
                    ->with('modaction.notes')
                    ->with('user')
                    ->with('modaction.user')
                    ->where('attributedTo', null)
                    ->get();

        $meta['showHeader'] = true;
        $meta['from'] = Carbon::now()->subDay();
        $meta['to'] = Carbon::now();
        $meta['user'] = "Pending Ratings";
        $meta['count'] = $contents->count();
        $meta['availableUsers'] = User::permission('mod.isAMod')->with('fjuser')->get();
        $meta['showRangePicker'] = false;
        return view('moderator.modaction')->with('contents', $contents)->with('meta', $meta);

    }

    public function attributeContent(FJContent $content, $userid)
    {
        $content->attributedTo = $userid;
        $content->save();
        $content->modaction()->latest('id')->first()->addNote('content_attribute', Auth::user()->fjuser->username . ' attributed content to ' . $userid);
    }

    public function removeNeedsReview($fjcontent)
    {
        $content = FJContent::findOrFail($fjcontent);
        $content->needsReview = false;
        $content->save();
        $content->modaction()->latest('id')->first()->addNote('content_review', Auth::user()->fjuser->username . ' acknowledged review');
        return ["OK"];
    }

    public function getNextContentNeedingReview($studentName)
    {
        $fjuser = FunnyjunkUser::where('username', $studentName)->firstOrFail();

        $content = FJContent::with('modaction')
                    ->with('modaction.notes')
                    ->with('user')
                    ->with('modaction.user')
                    ->where('attributedTo', $fjuser->fj_id)
                    ->where('needsReview', true)
                    ->first();

        return $content;
    }


    /*
    * Updates records with new times
    * Remove next update
    */
    public function updateRecords()
    {
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
        $input = $this->fj->getFlags();
        $input = json_decode($input, true);
        $input = collect($input)->map(function($row){
            return collect($row);
        });

        DB::transaction(function () use($input){
            foreach($input->chunk(1) as $chunk)
            {
                $chunk = $chunk->first();
                if($chunk->get('reference_type') == 'content')
                {
                    try{
                        $content = FJContent::findOrFail($chunk->get('reference_id'));
                        $content->created_at = $chunk->get('date');
                        $content->updated_at = $chunk->get('date');
                        $content->save();
                    } catch(ModelNotFoundException $e)
                    {   
                        echo "New Content Skipped";
                    }
                }
            }
        });
    }

    public function parseJson()
    {
        \Log::info('Parsing JSON');
        $this->fj = new FunnyJunk();
        $this->fj->login(env("FJ_USERNAME"), env("FJ_PASSWORD"));
        $input = $this->fj->getFlags();
        //$input = Storage::disk('local')->get('testing.json'); //DEV
        $latest = ModAction::whereRaw('id = (select max(`id`) from mod_actions)')->first();
        $input = json_decode($input, true);
        $input = collect($input)->map(function($row){
            return collect($row);
        });
        $input = $input->filter(function($value,$key) use ($latest){
            if($value["id"] > $latest->id)
            {
                return true;
            }
            else
            {
                return false;
            }
        });
        $input = $input->reverse();
        //need get latest and discard any old ones to optimise this crap lol
        DB::transaction(function () use($input){
            foreach($input->chunk(1) as $chunk)
            {  
                $chunk = $chunk->first();
                //if($chunk->get('reference_id') != 6713502)
                    //break;

                $action = ModAction::create($chunk->toArray());
                
                //Insert $chunk into mod table thing
                if($chunk->get('reference_type') == 'content')
                {
                    //ACTION RELATING TO CONTENT
                    try{
                        $content = FJContent::findOrFail($chunk->get('reference_id'));
                        $content->updated_at = $action->date;
                    } catch(ModelNotFoundException $e)
                    {   
                        $content = new FJContent;
                        $content->created_at = $action->date;
                    }
                    
                        
                    $content->id = $chunk->get('reference_id');
                    $content->url = $chunk->get('url');
                    $content->fullsize_image = $chunk->get('fullsize_image');
                    $content->thumbnail = $chunk->get('thumbnail');
                    $content->in_nsfw = $chunk->get('in_nsfw');
                    $content->flagged = $chunk->get('flagged');
                    $content->owner = $chunk->get('owner');
                    $content->title = $chunk->get('title');
                    $content->flagged = $chunk->get('flagged');

                    switch($chunk->get('category')){
                        case 'pc_level':
                            $level = $this->getLevelFromString($chunk->get('info'));
                            $content->rating_pc = $level;
                            break;
                        case 'skin_level':
                            $level = $this->getLevelFromString($chunk->get('info'));
                            $content->rating_skin = $level;
                            break;
                        case 'category': //rating_category
                            $category = $this->getCategoryFromString($chunk->get('info'));
                            $content->rating_category = $category;
                            break;
                        case 'flag':
                            $flag = $this->getLastWordFromString($chunk->get('info'));
                            $content->flagged_as = $flag;
                            break;
                        case 'unflag':
                            $content->flagged_as = null;
                            $content->hasIssue = true;
                            $action->addNote('fjmeme_parser_message', 'Issue raised due to content unflag');
                            break;
                    }

                    //action made by student
                    $modLevel = (int) filter_var($chunk->get('role_name'), FILTER_SANITIZE_NUMBER_INT);
                    if($modLevel <= 1)
                    {
                        $content->needsReview = true;
                        //$action->addNote('fjmeme_student', 'Content marked for review as moderator is a student');
                    }
                    
                    if($action->modifier != null)
                    {
                     //$action->addNote('fjmeme_parser_message', 'Issue raised due to modifier usage');
                     //$content->hasIssue = true;
                    }

                    //If content was never seen before, attribute it.
                    if($content->exists == false)
                        $content->attributedTo = $chunk->get('user_id');
                    else{
                        //If action was performed by not the original mod
                        if($content->attributedTo != $chunk->get('user_id')){
                            if(
                                $content->getOriginal('rating_pc') != $content->rating_pc ||
                                $content->getOriginal('rating_skin') != $content->rating_skin ||
                                $content->getOriginal('rating_category') != $content->rating_category ||
                                $content->getOriginal('flagged_as') != $content->flagged_as
                            ){
                                $content->attributedTo = null;
                                $action->addNote('fjmeme_parser_message', 'Attribution removed due to moderator conflict');
                            }
                        }

                    }

                    //If raised issue flag
                    if($content->getOriginal('hasIssue') == 0 && $content->hasIssue == true)
                    {
                        $slack = new Slack;
                        $slack->target = 'mod-notify';
                        $slack->username = 'Ratings Parser';
                        $slack->avatar = 'https://i.imgur.com/RoZ6aLY.jpg';
                        $slack->title = $content->title;
                        $slack->text = 'I have encountered an issue <@&790627250415599639>';
                        $slack->embedFields = ['Content ID' => $content->id, 'Issue' => $action->info];
                        $slack->footer = "Review: https://fjme.me/mods/contentInfo/" . $content->id;
                        $slack->color = "error";
                        \Notification::send($slack, new \App\Notifications\ModNotify(null));
                    }
                    $content->save();
                }

                if(in_array($chunk->get('category'), ['flag', 'comment_flag', 'spam_comment_flag'])) {
                    $patrol = UserFlagPatrol::where('type', strtoupper($chunk->get('reference_type')))->where('cid', $chunk->get('reference_id'))->first();
                    if($patrol != null)
                        $patrol->markAsPatrolled($chunk->get('user_id'), true);
                }

                if($action->modifier != null && $action->modifier > 1)
                {
                     $action->addNote('fjmeme_parser_message', 'Issue raised due to modifier usage');
                        $slack = new Slack;
                        $slack->target = 'mod-notify';
                        $slack->username = 'Jeff Beezos';
                        $slack->avatar = 'https://i.imgur.com/ANa87p5.png';
                        $slack->title = 'Modifier Used';
                        $slack->text = 'I have encountered a modifier being used <@&299311804113354763> ' . $action->url;
                        $slack->embedFields = ['Modifier' => $action->modifier];
                        $slack->color = "warning";
                        try {
                            \Notification::send($slack, new \App\Notifications\ModNotify(null));
                        } catch (Exception $e) {
                            $action->addNote('fjmeme_parser_error', "EXCEPTION: $e");
                        }
                }
                
                if(
                    in_array(
                        $chunk->get('category'), 
                        [
                         'mod_remove',
                         'mod_add'
                        ]
                    )
                ){
                    try{
                        $slack = new Slack;
                        $slack->target = 'mod-notify';
                        $slack->username = 'Moderation Supervisor';
                        $slack->avatar = 'https://i.imgur.com/9u7JJeX.png';
                        $slack->title = 'Administrative action detected';
                        $slack->text =  $chunk->get('info') . ' <@!66277163090841600> <@!222275959066329088> if this is a demod and there isnt a correspodning mod-social announcement, tell EdwardNigma to do it properly. ' . $chunk->get('url');
                        $slack->embedFields = ['Modifier' => $chunk->get('category')];
                        $slack->color = "warning";
                        \Notification::send($slack, new \App\Notifications\ModNotify(null));
                    } catch(Exception $e)
                    {
                        logger()->emergency('Notification failure', ['Notification failed', $chunk]);
                    }
                }
            }
        });

        $unratedContent = FJContent::where('attributedTo', null)->count();
        if($unratedContent > 30)
        {
            $slack = new Slack;
            $slack->target = 'mod-notify';
            $slack->username = 'Executive Whip';
            $slack->avatar = 'https://i.imgur.com/876EUYE.png';
            $slack->title = "Ratings Too High";
            $slack->text = 'Unresolved conflicts is at ' . $unratedContent . ' <@!185620305983700994> :gay_pride_flag: <@!191311168835420162> ';
            $slack->embedFields = [];
            $slack->footer = "Review: https://fjme.me/mods/ratings/nobody";
            $slack->color = "warning";
            \Notification::send($slack, new \App\Notifications\ModNotify(null));
        }

        return("DONE");
    }
    
    protected function getLevelFromString($string)
    {
        return (int) filter_var(($string), FILTER_SANITIZE_NUMBER_INT);
    }

    protected function getCategoryFromString($string)
    {
        if(preg_match('/"([^"]+)"/', $string, $result))
            return $result[1];
        else{
            throw new ModActionParseErrorException("CATEGORY Parsing Error " . $string);
        }
    }

    protected function getLastWordFromString($string)
    {
        $pieces = explode(' ', $string);
        $last_word = array_pop($pieces);
        return $last_word;
    }
}
