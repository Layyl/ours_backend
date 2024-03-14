<?php

namespace App\Http\Controllers\referral;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class uploadFilesController extends Controller
{
    public function upload(Request $request)
    {
        $uploadDirectory = 'C:\Users\IHOMS-TRISTAN\Desktop\jblmgh-ours\src\uploads';
        $maxFileSize = 30 * 1024 * 1024;
        
        if ($request->has('patientID')) {
            $patientID = $request->input('patientID');
            $patientDirectory = $uploadDirectory . '/'. $patientID . '/';
            
            if (!File::exists($patientDirectory)) {
                File::makeDirectory($patientDirectory, 0777, true);

            }

            // Calculate the total size of files in the patient's directory.
            $patientFiles = File::files($patientDirectory);
            $totalPatientFileSize = array_reduce($patientFiles, function ($carry, $file) {
                return $carry + File::size($file);
            }, 0);

            if ($request->hasFile('files')) {
                $uploadedFilenames = [];

                foreach ($request->file('files') as $file) {
                    $uploadedFilename = $file->getClientOriginalName();
                    $uploadPath = $patientDirectory . $uploadedFilename;
                    $fileSize = $file->getSize();

                    // Check if adding the current file size to the patient's total size exceeds the maximum file size limit.
                    if (($totalPatientFileSize + $fileSize) <= $maxFileSize) {
                        $file->move($patientDirectory, $uploadedFilename);
                        $uploadedFilenames[] = $uploadedFilename;
                        $totalPatientFileSize += $fileSize; // Update the total patient file size counter.
                    } else {
                        // Delete the files that have been moved.
                        foreach ($uploadedFilenames as $filenameToDelete) {
                            File::delete($patientDirectory . $filenameToDelete);
                        }

                        return response()->json(['error' => 'Total patient file size exceeds the maximum limit of 30MB.'], 413);
                    }
                }

                return response()->json(['message' => 'Files uploaded successfully', 'filenames' => $uploadedFilenames]);
            } else {
                return response()->json(['error' => 'No files were uploaded.']);
            }
        } else {
            return response()->json(['error' => 'Missing patientID.']);
        }
    }
}
