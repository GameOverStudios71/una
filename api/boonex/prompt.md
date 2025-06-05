# Mapeamento de API RESTful para o Sistema UNA

## Objetivo
O objetivo deste projeto é realizar um mapeamento completo do sistema UNA para transformá-lo em uma API RESTful robusta. Isso envolve a análise detalhada de todas as classes, métodos e funcionalidades de cada módulo, documentando-os em arquivos de texto específicos (ex.: `posts_funcionalidades.txt`) dentro do diretório api e mapeando cada funcionalidade para rotas RESTful. Este trabalho serve como base para a futura implementação de serviços backend que retornem JSON, mantendo a dualidade com o frontend existente.

## O que está sendo feito
O processo inclui:
- **Análise de Classes**: Examination de cada arquivo PHP no diretório `classes` do módulo Ex: `posts` para identificar classes, métodos e suas funcionalidades.
- **Documentação**: Registro detalhado de cada classe e seus métodos no arquivo `posts_funcionalidades.txt` dentro do diretório api, incluindo descrições e propósitos.
- **Mapeamento para Rotas RESTful**: Criação de rotas RESTful correspondentes para cada funcionalidade identificada, garantindo que todas as operações possam ser acessadas via API.

## Como continuar o trabalho
Caso precise continuar ou revisar este mapeamento, siga estes passos:
1. **Localize os Arquivos de Documentação**: Os arquivos de mapeamento estão no diretório api, como `c:\xampp\htdocs\una\api\posts_funcionalidades.txt` para o módulo `posts`.
2. **Analise Classes Pendentes**: Liste os arquivos no diretório `classes` do módulo (ex.: `c:\xampp\htdocs\una\modules\boonex\posts\classes`) e verifique quais ainda não foram documentados no arquivo de funcionalidades.
3. **Documente Novas Classes**: Para cada classe não documentada, leia o código-fonte, descreva a classe, seus métodos principais e propósito no arquivo de funcionalidades.
4. **Mapeie Rotas RESTful**: Com base nas funcionalidades da classe, proponha rotas RESTful que correspondam às operações seguindo o padrão de nomenclatura e estrutura já estabelecido (ex.: `GET /api/posts/...`). adicione as rotas para todas as funcionalidades porque é importante que todas as funcionalidades sejam mapeadas para rotas RESTful.
5. **Remova Seções Desnecessárias**: não precisa escrever  'Notas e Próximos Passos' manter o arquivo limpo, 
6. **Prossiga para Outros Módulos**: Após completar o mapeamento do módulo, passe para outros módulos repetindo o processo até documentar todos os módulos do sistema. Utilize os arquivos dentro do diretório api como referência para saber quais módulos já foram documentados. responda sempre em português, não precisa fazer módulos que funcionam como idiomas, autenticação de usuários para outros provedores e nem templates

## Exemplos de Documentação e Mapeamento

### Descrição Geral do Módulo
O módulo 'posts' gerencia a criação, edição, exclusão e visualização de posts no sistema UNA. É um componente central para funcionalidades de conteúdo, possivelmente integrado a timelines, feeds ou blogs. É composto por classes como BxPostsModule.php (lógica específica do módulo) e outras classes de suporte para formulários, buscas, configurações e templates.

### Exemplo de Análise de Classe
No arquivo `posts_funcionalidades.txt`, a análise de uma classe pode ser vista como:
```
Análise de BxPostsModule.php (Classe Principal do Módulo Posts):
- BxPostsModule extends BxBaseModTextModule: Classe principal para gerenciar a lógica central do módulo posts.
- __construct(&$aModule): Construtor que chama o construtor da classe pai com o módulo fornecido e configura campos não pesquisáveis.
- serviceGetTimelinePost($mixedContent, $sType = 'id', $iStart = 0, $iPerPage = 10, $sFilter = '', $sTimeline = 'all', $sModule = ''): Método para obter posts para timeline.
- serviceCheckAllowedCommentsPost($iContentId, $sObjectComments): Método para verificar se comentários são permitidos em um post específico.
- serviceCheckAllowedCommentsView($iContentId, $sObjectComments): Método para verificar se a visualização de comentários é permitida em um post específico.
```

### Exemplo de Mapeamento para Rotas RESTful
Após a análise, as funcionalidades são mapeadas para rotas RESTful, como:
```
Mapeamento para Rotas RESTful (Baseado em Funcionalidades Principais):
- GET /api/posts/timeline: Obtém dados de posts para timeline (criar serviço para retornar posts ajustados para timeline).
- POST /api/posts/{id}/comments/allow: Configura permissão para comentários (derivado de serviceCheckAllowedCommentsPost e serviceCheckAllowedCommentsView).
- GET /api/posts/{id}/comments/allow: Verifica se a visualização de comentários é permitida em um post específico.
```

## Notas Finais
Este mapeamento é crucial para garantir que nenhuma funcionalidade do sistema UNA seja perdida na transição para uma API RESTful. Após completar a documentação de todos os módulos, o próximo passo será implementar os serviços mapeados em `api.php`, testando cada rota para garantir funcionalidade tanto no backend quanto na integração com o frontend. Se precisar de assistência ou tiver dúvidas sobre como prosseguir, estou à disposição para ajudar.
