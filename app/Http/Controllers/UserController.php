<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image as Image;


class UserController extends Controller
{
    // UNSECURE
    // public function show($id)
    // {
    // 	$user = User::findOrFail($id);

    //     return view('auth.profile',compact('user'));
    // }

    // SECURE
    public function profile()
    {
        if (!$user = Auth::user())
            return response()->json(['message' => 'Forbidden Operation'], 403);

        return view('auth.profile', compact('user'));
    }

    public function update(Request $request, $id)
    {
        $user = User::find($id);

        $user->update($request->all());

        return back()->with('message', 'User updated');
    }
    public function changeEmail(Request $request)
    {

        if (!$user = Auth::user())
            return response()->json(['message' => 'Forbidden Operation'], 403);

        $user->email = $request->email;
        $user->save();

        return back()->with('message', 'Changed successfully');
    }

    public function changeName(Request $request)
    {
        if (!$user = Auth::user())
            return response()->json(['message' => 'Forbidden Operation'], 403);

        $user->name = $request->name;
        $user->save();

        return back()->with('message', 'Changed successfully');
    }

    public function changeImg(Request $request)
    {
        if (!$user = Auth::user()) {
            return back()->with('message', 'Please Log In');
        }

        if (!$request->hasFile('avatar')) {
            return back()->with('message', 'Forbidden Operation');
        }

        if (!file_exists(storage_path("app/public/images/users/" . $user->id))) {
            mkdir(storage_path("app/public/images/users/" . $user->id), 0777, true);
        }

        // retrieve uploaded image
        $newImage = $request->file('avatar');
        // calculate hash

        // UNSECURE with md5
        $newImageHash = md5_file($newImage);

        // SECURE with sha56
        // $newImageHash = hash_file('sha256', $newImage);

        // compare hash
        if ($newImageHash == $user->avatar) {
            return redirect()->back()->with('message', 'Image not updated, same');
        }
        // Define the path to store the image
        $path = "images/users/" . $user->id;


        Storage::deleteDirectory($path);


        // Store the image in the defined path
        $filePath = $newImage->storeAs($path, $newImageHash, 'public');

        // save new user avatar name
        $user->avatar = $newImageHash;
        $user->save();

        return redirect()->back()->with('message', 'Image updated');
    }

    public function upload(Request $request)
    {
        if (!$user = Auth::user()) {
            return back()->with('message', 'Please Log In');
        }
        if (!$request->hasFile('file')) {
            return back()->withErrors('Forbidden Operation');
        }

        // SECURE: define allowed extensions and mime types
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
        $allowedMimeTypes  = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

        $file      = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType  = $file->getMimeType();

        if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            return back()->withErrors('File type not allowed');
        }

        $filename = $file->getClientOriginalName();

        // generate a unique id for the file
        $fileUid = uniqid() . '.' . $extension;

        // save the file in a private folder (storage/app/private/docs/users/{id})
        $file->storeAs("docs/users/{$user->id}", $fileUid, 'local');

        // save the record in the DB
        File::create([
            'name'    => $filename,
            'uid'     => $fileUid,
            'user_id' => $user->id
        ]);

        return back()->with('message', 'Upload successful');
    }

    // UNSECURE
    // public function download(Request $request)
    // {
    //     return response()->download(storage_path('app/private/' . $request->get('filename')));
    // }

    // SECURE
    public function downloadPrivateFile($uid)
    {
        $file = File::where('uid', $uid)->firstOrFail();

        if ($file->user_id !== Auth::id()) {
            return response()->json(['message' => 'Forbidden Operation'], 403);
        }

        $path = storage_path('app/private/docs/users/' . $file->user_id . '/' . $uid);

        if (!file_exists($path)) {
            abort(404);
        }

        return response()->download($path, $file->name);
    }
}
