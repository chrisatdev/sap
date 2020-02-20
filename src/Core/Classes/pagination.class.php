<?php
namespace Classes\listing;

class Pagination extends \AbstractionModel{

    public $limiting = 10;

    public $from,$fields_list;

    public function listing( $params ){

        extract($params, EXTR_OVERWRITE);
        
        if (isset($where)) {
            $options['where'] = $where;
        }

        if (isset($join)) {
            $options['join'] = $params['join'];
        }

        if (isset($order)) {
            $options['order'] = $order;
        }

        if (is_numeric( $this->limiting )) {
            $starting = ($page - 1) * $this->limiting;
        }

        $total_rows = $this->select( $options );

        $totalRows = count( $total_rows );

        $totalPages  = ceil( $totalRows / $this->limiting);

        if ($this->limiting > 0) {
            $options['limit'] = $starting . ', ' . $this->limiting;
        }

        $listing = $this->select( $options );

        return [
            'list'          => $listing,
            'pages'         => $totalPages,
            'page'          => $page,
            'total_rows'    => $totalRows,
            'navigation'    => $this->navigation( $totalPages, $page )
        ];
    }

    private function navigation( $pages, $page )
    {
        $navigation = [];

        if ($pages > 1) :

            $back = $page - 1;
            $back = ($back <= 1) ? 1 : $back;

            $next = $page + 1;
            $next = ($next >= $pages) ? $pages : $next;

            $last = $pages;

            $navigation['back'] = $back;

            $i = 1;

            $p = 10;

            if ($pages > 10) :
                $i = $page;
                $p = $page + 10;
            endif;

            if ($p >= $pages) :
                if ($pages > 10) :
                    $i = $pages - 10;
                    if ($i <= 0) :
                        $i = 1;
                    endif;
                endif;
                $p = $pages;
            endif;

            if ($last > $p) :
                $p = $p - 1;
            endif;

            for ($x = $i; $x <= $p; $x++) :
                $navigation['pages'][] = $x;
            endfor;

            if ($last > $p) :
                $navigation['last'] = $last;
            endif;

            $navigation['next'] = $next;
        endif;
        return $navigation;
    }
}