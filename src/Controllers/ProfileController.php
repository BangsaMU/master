<?php

namespace Bangsamu\Master\Controllers;

use App\Http\Controllers\Controller;
use Bangsamu\Master\Rules\ProfileUpdateRequest;
use App\Models\User; // Import UserDetail
use App\Models\UserDetail; // Import UserDetail
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request; // Keep Request imported if you need to use it directly
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage; // Import Storage
use Illuminate\Support\Facades\DB; // Import DB
use Illuminate\Support\Facades\Hash; // Import Hash if needed for password
use Illuminate\Support\Facades\Log; // Import Log facade
use Bangsamu\LibraryClay\Controllers\LibraryClayController;

class ProfileController extends Controller
{
    protected $readonly = false;
    protected $sheet_name = 'Profile'; //nama label untuk FE
    protected $sheet_slug = 'profile'; //nama routing (slug)

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request)
    {
        $sheet_name = $this->sheet_name;
        $sheet_slug = $this->sheet_slug;
        $user = Auth::user();

        $user = auth()->user();

        return view('master::master'.config('app.themes').'.' . $this->sheet_slug . '.edit', compact('user'));
        // return view('master::profile.edit', [
        //     'user' => Auth::user(),
        // ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // Use a transaction to ensure atomicity for profile and user details updates
        DB::beginTransaction();

        try {
            $user = $request->user();

            // Fill standard user fields (name, email) from validated request
            // $user->fill($request->validated());

            // if ($user->isDirty('email')) {
            //     $user->email_verified_at = null;
            // }

            // Handle password update if it's filled in the request
            // if ($request->filled('password')) {
            //     $user->password = Hash::make($request->password);
            // }

            // $user->save();
            // --- Signature Handling ---
            // The logic here is identical to your UserController, but targeting $user
            // dd($request->signature, $request->paraf,$request->all());
            if($request->signature){
                $this->handleImageUpload($request, $user, 'signature', 'signatures');
            }

            // --- Initials/Paraf Handling ---
            if($request->paraf){
                $this->handleImageUpload($request, $user, 'paraf', 'parafs');
            }

            DB::commit();


            $sync_row = $user->toArray();
            if (!empty($sync_row)) {
                /*sync callback*/
                $id =  $user->email;// khusu update user pakek email lainnya id
                $sync_tabel = 'master_user';
                $sync_id = $id;
                $key_unik = 'email'; //default id
                // $sync_row['deleted_at'] = null;
                $sync_list_callback = config('AppConfig.CALLBACK_URL');
                //update ke master DB saja
                if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                    $callbackSyncMaster = LibraryClayController::updateMaster(compact('key_unik','sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                }
                $message = $this->sheet_name . ' updated successfully';
            } else {
                $message = $this->sheet_name . ' has no data to sync';
            }

            return Redirect::route('profile.edit')->with('status', 'profile-updated');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error updating user profile (ID: {$request->user()->id}): " . $e->getMessage(), ['exception' => $e]);
            // You might want a different flash message or redirect for errors on the profile page
            return Redirect::route('profile.edit')->with('error', 'Error updating profile: ' . $e->getMessage());
        }
    }

    /**
     * Helper method to handle signature/paraf image uploads and deletions.
     *
     * @param \Illuminate\Http\Request $request The current request instance.
     * @param \App\Models\User $user The user model being updated.
     * @param string $fieldKey The field key (e.g., 'signature', 'paraf').
     * @param string $directory The storage directory (e.g., 'signatures', 'parafs').
     * @return void
     */
    protected function handleImageUpload(Request $request, User $user, string $fieldKey, string $directory)
    {
        if ($request->filled($fieldKey)) {
            // Check if the data URL starts with "data:image/", if not, it might be a valid URL already stored
            if (strpos($request->$fieldKey, 'data:image/') === 0) {
                list($type, $data) = explode(';', $request->$fieldKey);
                list(, $data)      = explode(',', $data);
                $decodedImage = base64_decode($data);

                if (!Storage::disk('media')->exists($directory)) {
                    Storage::disk('media')->makeDirectory($directory);
                }

                $filename = $directory . '/' . $user->id . '_' . $fieldKey . '_' . uniqid() . '.png';
                Storage::disk('media')->put($filename, $decodedImage);

                $oldImageDetail = $user->details()->where('field_key', $fieldKey)->first();
                if ($oldImageDetail && $oldImageDetail->field_value) {
                    Storage::disk('media')->delete($oldImageDetail->field_value);
                }

                $UserDetail = UserDetail::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'field_key' => $fieldKey,
                    ],
                    [
                        'field_value' => $filename,
                        'user_email' => $user->email,
                    ]
                );

                $sync_row = $UserDetail->toArray();

                if (!empty($sync_row)) {
                    /*sync callback*/
                    $id =  $user->email;// khusu update user pakek email lainnya id
                    $sync_tabel = 'master_user_details';
                    $sync_id = $id;
                    $key_unik = 'user_email'; //default id
                    // $sync_row['deleted_at'] = null;
                    $sync_list_callback = config('AppConfig.CALLBACK_URL');
                    //update ke master DB saja
                    if (config('MasterCrudConfig.MASTER_DIRECT_EDIT')) {
                        $callbackSyncMaster = LibraryClayController::updateMaster(compact('fieldKey','key_unik','sync_tabel', 'sync_id', 'sync_row', 'sync_list_callback'));
                    }
                    $message = $this->sheet_name . ' updated successfully';
                } else {
                    $message = $this->sheet_name . ' has no data to sync';
                }


            } else {
                // If it's filled but not a data URL, assume it's an existing URL
                // and do nothing, or re-save if you want to explicitly confirm.
                // For a profile update, if it's not a data URL, it likely means
                // the user didn't change the signature/paraf and the field
                // contained the existing URL for preview, which is fine.
            }
        }
        // elseif ($request->exists($fieldKey)) { // Handle deletion if input is empty string
        //     // $imageDetail = $user->details()->where('field_key', $fieldKey)->first();
        //     // if ($imageDetail) {
        //     //     Storage::disk('media')->delete($imageDetail->field_value);
        //     //     $imageDetail->delete();
        //     // }
        // }
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
