<?php

namespace MGLara\Models;

/**
 * Campos
 * @property  bigint                         $codfamiliaproduto                  NOT NULL DEFAULT nextval('tblfamiliaproduto_codfamiliaproduto_seq'::regclass)
 * @property  varchar(50)                    $familiaproduto                     NOT NULL
 * @property  timestamp                      $inativo                            
 * @property  timestamp                      $alteracao                          
 * @property  bigint                         $codusuarioalteracao                
 * @property  timestamp                      $criacao                            
 * @property  bigint                         $codusuariocriacao                  
 * @property  bigint                         $codimagem                          
 * @property  bigint                         $codsecaoproduto                    NOT NULL
 *
 * Chaves Estrangeiras
 * @property  Usuario                        $UsuarioCriacao
 * @property  Usuario                        $UsuarioAlteracao
 * @property  Imagem                         $Imagem                        
 * @property  SecaoProduto                   $SecaoProduto                  
 *
 * Tabelas Filhas
 * @property  Grupoproduto[]                 $GrupoprodutoS
 */

class FamiliaProduto extends MGModel
{
    protected $table = 'tblfamiliaproduto';
    protected $primaryKey = 'codfamiliaproduto';
    protected $fillable = [
        'familiaproduto',
        'inativo',
        'codimagem',
        'codsecaoproduto',
    ];
    protected $dates = [
        'inativo',
        'alteracao',
        'criacao',
    ];

    public function validate() {
        
        if ($this->codfamiliaproduto) {
            $unique_familiaproduto = 'unique:tblfamiliaproduto,familiaproduto,'.$this->codfamiliaproduto.',codfamiliaproduto';
        } else {
            $unique_familiaproduto = 'unique:tblfamiliaproduto,familiaproduto';
        }
        
        $this->_regrasValidacao = [
            'codsecaoproduto' => 'required', 
            'familiaproduto' => "required|min:3|$unique_familiaproduto", 
        ];    
        $this->_mensagensErro = [
            'codsecaoproduto.required'  => 'Selecione uma Seção de produto.',
            'familiaproduto.required'   => 'Família de produto nao pode ser vazio.',
            'familiaproduto.min'        => 'Família de produto deve ter mais de 3 caracteres.',
            'familiaproduto.unique'     => 'Esta Família já esta cadastrada',
        ];

        return parent::validate();
    }    

    // Chaves Estrangeiras
    public function UsuarioCriacao()
    {
        return $this->belongsTo(Usuario::class, 'codusuario', 'codusuariocriacao');
    }

    public function UsuarioAlteracao()
    {
        return $this->belongsTo(Usuario::class, 'codusuario', 'codusuarioalteracao');
    }

    public function Imagem()
    {
        return $this->belongsTo(Imagem::class, 'codimagem', 'codimagem');
    }

    public function SecaoProduto()
    {
        return $this->belongsTo(SecaoProduto::class, 'codsecaoproduto', 'codsecaoproduto');
    }


    // Tabelas Filhas
    public function GrupoProdutoS()
    {
        return $this->hasMany(GrupoProduto::class, 'codfamiliaproduto', 'codfamiliaproduto');
    }

    public static function search($parametros, $registros = 20)
    {
        $query = FamiliaProduto::orderBy('familiaproduto', 'ASC');
        
        if(isset($parametros['codsecaoproduto']))
            $query->where('codsecaoproduto', $parametros['codsecaoproduto']);
            
        if(isset($parametros['codfamiliaproduto']))
            $query->id($parametros['codfamiliaproduto']);
        
        if(isset($parametros['familiaproduto']))
            $query->familiaProduto($parametros['familiaproduto']);
        
        if(isset($parametros['inativo']))
            switch ($parametros['inativo'])
            {
                case 9: // Todos
                    break;
                case 2: // Inativos
                    $query->inativo();      break;
                default:
                    $query->ativo();        break;
            }
        else
            $query->ativo();
        
        return $query->paginate($registros);
    }
    
    public function scopeId($query, $id)
    {
        if (trim($id) === '')
            return;
        
        $query->where('codfamiliaproduto', $id);
    }
    
    public function scopeFamiliaProduto($query, $familiaproduto)
    {
        if (trim($familiaproduto) === '')
            return;
        
        $familiaproduto = explode(' ', removeAcentos($familiaproduto));
        foreach ($familiaproduto as $str)
            $query->where('familiaproduto', 'ILIKE', "%$str%");
    }    
    
    public function scopeInativo($query)
    {
        $query->whereNotNull('inativo');
    }

    public function scopeAtivo($query)
    {
        $query->whereNull('inativo');
    }
}
