<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFolderRequest;
use App\Http\Requests\UpdateFolderRequest;
use App\Models\Folder;
use Illuminate\Http\Response;

class FolderController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreFolderRequest $request
     * @return Response
     */
    public function store(StoreFolderRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param Folder $folder
     * @return Response
     */
    public function show(Folder $folder)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Folder $folder
     * @return Response
     */
    public function edit(Folder $folder)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateFolderRequest $request
     * @param Folder $folder
     * @return Response
     */
    public function update(UpdateFolderRequest $request, Folder $folder)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Folder $folder
     * @return Response
     */
    public function destroy(Folder $folder)
    {
        //
    }
}
