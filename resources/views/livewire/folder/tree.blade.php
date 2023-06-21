<div>
    {{--
        <li><a>Sidebar Item 1</a></li>
        <li><a>Sidebar Item 1</a></li>
        <li><a>Sidebar Item 2</a></li>
    --}}
    <div class="tree">
        @include('components.folder.tree', ['folders' => $folders])
        {{--
                <ul>
                    <li><a><i class="fa fa-folder-open"></i> Project</a>
                        <ul>
                            <li><a><i class="fa fa-folder-open"></i> Opened Folder <span>- 15kb</span></a>
                                <ul>
                                    <li><a><i class="fa fa-folder-open"></i> css</a>
                                        <ul>
                                            <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                            </li>
                                            <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                            </li>
                                            <li><a><i class="fa fa-code"></i> CSS Files <span>- 3kb</span></a>
                                            </li>
                                        </ul>
                                    </li>
                                    <li><a><i class="fa fa-folder"></i> Folder close <span>- 10kb</span></a>
                                    </li>
                                    <li><a><i class="fab fa-html5"></i> index.html</li>
                                    </a>
                                    <li><a><i class="fa fa-picture-o"></i> favicon.ico</li>
                                    </a>
                                </ul>
                            </li>
                            <li><a><i class="fa fa-folder"></i> Folder close <span>- 420kb</span></a>
                            </li>
                        </ul>
                    </li>
                </ul>
        --}}
    </div>

</div>
