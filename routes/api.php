<?php

use App\Gene;
use App\Phenotype;
use App\Services\FeedService;
use App\Study;
use App\StudyGene;
use App\UserFeed;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

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

Route::post("contact", function(Request $request){

    $data = [
        'from_name' => $request->input('name'),
        'reply_to' => $request->input('email'),
        'subject' => $request->input('subject'),
        'message' => $request->input('message')
    ];

    $contact_email = new \App\Mail\Contact($data);

    Mail::to(config('custom.contact_from_recipient'))
        ->send( $contact_email );
})->name("contact");

Route::post("studies", function (Request $request) {
    $params = [
        ''
    ];

    /**
     * 1.) Create the study in database
     * 2.) Run a match operation to retrieve a list of users
     * 3.) Populate the user news feed
     */
});

Route::get("feed", function(Request $request){
    // returns entire feed, old and new
    $user = User::first();
    return $user->feed()->with(['study','study.phenotype'])->get();
});

Route::get("feed/unread", function(Request $request){
    // returns items from the feed that are marked "unread"
    $user = User::first();
    return $user->feed()->with(['study','study.phenotype'])->where('read', '=', 0)->get();
});

Route::get("feed/{feed}/mark-read", function($feed_id, Request $request){
    $feed = UserFeed::find($feed_id);
    if ($feed === null) {
        return response()->json([
            'status' => "FEED_NOT_FOUND",
            'msg' => "Damnit Chris"
        ], 404);
    }
    $feed->read = true;
    $feed->save();
});

Route::post("studies", function (Request $request, FeedService $feedService) {
    $traitName = $request->trait;
    $studyParams = $request->study;
    $genes = $request->genes;

    try {
        DB::transaction( function () use($feedService, $traitName, $studyParams, $genes) {
            $trait = Phenotype::retrieveOrCreate(['name' => $traitName]);
            $studyParams['trait_id'] = $trait->getKey();
            $study = Study::retrieveOrCreate($studyParams);

            foreach ($genes as $newGene) {
                $gene = Gene::retrieveOrCreate($newGene);

                $edge = new StudyGene([
                    'odds_ratio' => $newGene['odds_ratio'],
                    'allele' => strtoupper($newGene['allele']),
                ]);
                $edge->study_id = $study->getKey();
                $edge->gene_id = $gene->getKey();
                $edge->save();
                $feedService->addToFeeds($edge);
            }

        } );
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'INVALID_DATA',
            'msg' => [
                $e->getMessage(),
                $e->getFile().":".$e->getLine(),
            ],
        ], 400);
    }

});

Route::post("variant", function (Request $request){
    $user = User::first();
    $allel = $request->allel;
    $variant = $request->variant;

    $gene = Gene::where('name', '=', $variant)->first();
    if ($gene === null) {
        $gene = Gene::create([
            "name" => $variant
        ]);
    }

    $user_gene = \App\UserGene::create([
        "user_id" => $user->id,
        "gene_id" => $gene->id,
        "allele" => $allel
    ]);

    return Redirect::to(route("profile"));
})->name("variant");

Route::delete("variant/{id}", function ($id, Request $request){

    \App\UserGene::where('gene_id', '=', $id)->delete();

    return response("Success");
})->name("delete_variant");
