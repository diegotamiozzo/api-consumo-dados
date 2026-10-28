# API Consumo DB

API PHP para consumir dados de tabelas MySQL associadas a clientes por meio de uma API key.

## Requisitos

- PHP 7.4 ou superior, com PDO MySQL
- MySQL
- Extensão cURL para executar `consumir_api.php`

## Configuração

Copie `.env.example` para `.env` e preencha as credenciais do banco e a associação entre API key e cliente:

```dotenv
DB_HOST=seu_host
DB_NAME=seu_banco
DB_USER=seu_usuario
DB_PASS=sua_senha
API_KEY=chave_secreta:7,8
QUERY_LIMIT=100
API_BASE_URL=http://localhost:8000
```

`API_KEY` usa o formato `chave:id_cliente[,id_cliente]`. Uma mesma chave pode acessar um ou mais clientes, por exemplo `chave_secreta:7,8`. Para configurar chaves com permissões distintas, separe as associações com `|`, por exemplo `chave1:7,8|chave2:8`.
`QUERY_LIMIT` define quantos registros a API retorna por consulta; `last_data_index` é informado na própria consulta, não no `.env`.

O cliente é associado a uma tabela pelo sufixo: o ID `7` corresponde a uma tabela terminada em `_07_data`, o ID `8` a `_08_data`, e assim por diante. Deve existir exatamente uma tabela correspondente por cliente no banco configurado. A tabela deve conter `data_index`, `data_format_0` e `time@timestamp`.

## Executar e testar

Inicie o servidor local:

```bash
php -S localhost:8000
```

Em outro terminal, execute o consumidor de teste. Ele usa a primeira API key configurada no `.env`; informe o cliente autorizado e, opcionalmente, o cursor:

```bash
php consumir_api.php 7 876666
```

O primeiro argumento é o `id_cliente`; o segundo é o `last_data_index` já recebido anteriormente (opcional, padrão `0`). A API retorna até `QUERY_LIMIT` registros com índice maior que esse cursor, em ordem crescente. O resultado mostra o cursor para a próxima chamada. Também é possível executar o endpoint diretamente pela CLI:

```bash
php index.php
```

Para consultar via HTTP, informe o cliente e o cursor na URL:

```bash
curl -H "API-KEY: chave_secreta" "http://localhost:8000/index.php?id_cliente=7&last_data_index=876666"
```

## API

**GET `/index.php?id_cliente=7&last_data_index=0`**

O header `API-KEY` é obrigatório. `id_cliente` também é obrigatório, e a API só consulta o cliente se ele estiver associado à chave no `.env`; enviar um cliente fora das permissões da chave retorna `403`. `last_data_index` é o cursor do último registro já processado. A API consulta os próximos registros (`data_index` maior que o cursor), em ordem crescente, limitados por `QUERY_LIMIT` (100 por padrão no `.env.example`).

O campo `data_format_0` é dividido por 10 na resposta:

```json
{
  "valor": {
    "last_data_index": 876666,
    "data": [
      {
        "data_index": 876666,
        "id_cliente": 7,
        "data_format_0": 11,
        "time@timestamp": 1785247564.35
      }
    ]
  }
}
```

Use `valor.last_data_index` como `last_data_index` na chamada seguinte. Se não houver registros novos, o cursor informado é mantido.

## Erros HTTP

- `400`: `id_cliente` ou cursor inválido
- `401`: API key ausente ou inválida
- `403`: API key sem permissão para o cliente informado
- `500`: configuração, tabela ou banco de dados indisponível
