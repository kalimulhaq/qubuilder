<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Query String Parameters Name
    |--------------------------------------------------------------------------
    |
    | The names of query string parameters from the request to be used for
    | filtering inputs. If not provided, default parameter names will be used.
    |
     */
    'params' => [
        /**
         * The query string parameter to use for the `select`.
         * Default is `select`.
         */
        'select' => null,

        /**
         * The query string parameter to use for the `filter`.
         * Default is `filter`.
         */
        'filter' => null,

        /**
         * The query string parameter to use for the `include`.
         * Default is `include`.
         */
        'include' => null,

        /**
         * The query string parameter to use for the `sort`.
         * Default is `sort`.
         */
        'sort' => null,

        /**
         * The query string parameter to use for the `group`.
         * Default is `group`.
         */
        'group' => null,

        /**
         * The query string parameter to use for the `page`.
         * Default is `page`.
         */
        'page' => null,

        /**
         * The query string parameter to use for the `limit`.
         * Default is `limit`.
         */
        'limit' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Limit
    |--------------------------------------------------------------------------
    |
    | The number of records to retrieve by default if no limit was provided.
    |
     */
    'limit' => [
        'default' => 15,
        'max' => 50,
    ],
];
