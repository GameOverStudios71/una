import requests
import json
from typing import Dict, List, Any

# Base URL da API REST do DeeperHub
API_BASE_URL = "http://localhost:4000/api"  # Substitua pela URL correta da sua API

def fetch_api_data(endpoint: str) -> Dict[str, Any]:
    """Faz uma chamada para a API e retorna os dados JSON."""
    try:
        url = f"{API_BASE_URL}{endpoint}"
        print(f"Tentando acessar: {url}")
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        data = response.json()
        print(f"Resposta da API: {len(data)} itens recebidos do endpoint {endpoint}")
        return data
    except requests.exceptions.RequestException as e:
        print(f"Erro ao acessar {endpoint}: {e}")
        return {}
    except ValueError as e:
        print(f"Erro ao decodificar JSON de {endpoint}: {e}")
        return {}

def build_dynamic_menu(pages_data: List[Dict[str, Any]]) -> Dict[str, Dict[str, Any]]:
    """Constrói o menu dinamicamente com base nos dados das páginas da API."""
    menu = {}
    for i, page in enumerate(pages_data, 1):
        page_id = str(i)
        page_name = page.get('name', f"Página {page_id}")
        # Tenta inferir o endpoint a partir do nome ou icon, se disponível
        page_endpoint = f"/{page_name.lower()}"
        if page.get('icon') and '@modules/' in page.get('icon', ''):
            module_info = page.get('icon', '').split('@modules/')[1].split('/|')[0]
            page_endpoint = f"/bx_{module_info.split('/')[1]}"
        page_description = page.get('caption', f"Acessar {page_name}")
        menu[page_id] = {
            "name": page_name,
            "endpoint": page_endpoint,
            "description": page_description
        }
    menu[str(len(pages_data) + 1)] = {
        "name": "Sair",
        "endpoint": None,
        "description": "Sair do navegador textual."
    }
    return menu

def display_menu(options: Dict[str, Dict[str, Any]]) -> None:
    """Exibe o menu de opções para o usuário."""
    print("\n=== Navegador Textual DeeperHub ===")
    for key, page in options.items():
        print(f"{key}. {page['name']} - {page['description']}")
    print("====================================")

def display_json_data(data: Dict[str, Any], indent: int = 0) -> None:
    """Exibe os dados JSON de forma formatada."""
    if not data:
        print("Nenhum dado retornado ou erro na API.")
        return
    print("\n=== Dados da API ===")
    print(json.dumps(data, indent=2)[:1000])  # Limita a saída para não sobrecarregar o terminal
    if len(json.dumps(data)) > 1000:
        print("... (Dados truncados para exibição. Veja o log completo se necessário.)")
    print("====================")

def infer_options_from_data(data: Dict[str, Any], current_page: Dict[str, Any]) -> List[Dict[str, Any]]:
    """Tenta inferir opções adicionais com base nos dados JSON retornados."""
    options = []
    # Se os dados contiverem uma lista de itens, oferece para ver detalhes de um item
    if isinstance(data, list) and len(data) > 0:
        for i, item in enumerate(data[:5]):  # Limita a 5 itens para não sobrecarregar
            item_id = item.get('id', f'Item {i+1}')
            options.append({"name": f"Ver Detalhes - {current_page['name']} ID {item_id}", "action": "detail", "id": item_id})
    elif isinstance(data, dict):
        item_id = data.get('id', 'Item Único')
        options.append({"name": f"Ver Detalhes - {current_page['name']} ID {item_id}", "action": "detail", "id": item_id})
    # Adiciona opção para voltar ao menu principal
    options.append({"name": "Voltar ao Menu Principal", "action": "back"})
    return options

def display_additional_options(options: List[Dict[str, Any]]) -> str:
    """Exibe opções adicionais inferidas dos dados e retorna a escolha do usuário."""
    if not options:
        print("Nenhuma opção adicional disponível.")
        input("Pressione Enter para voltar ao menu principal...")
        return "back"
    print("\n=== Opções Adicionais ===")
    for i, opt in enumerate(options, 1):
        print(f"{i}. {opt['name']}")
    print("=========================")
    choice = input("Escolha uma opção adicional (ou Enter para voltar ao menu): ")
    if choice.strip() == "":
        return "back"
    try:
        idx = int(choice) - 1
        if 0 <= idx < len(options):
            return options[idx]
    except ValueError:
        pass
    print("Opção inválida.")
    return display_additional_options(options)

def fetch_api_data(endpoint: str) -> Dict[str, Any]:
    """Faz uma chamada para a API e retorna os dados JSON."""
    try:
        url = f"{API_BASE_URL}{endpoint}"
        print(f"Tentando acessar: {url}")
        response = requests.get(url, timeout=10)
        response.raise_for_status()
        data = response.json()
        print(f"Resposta da API: {type(data).__name__} recebido do endpoint {endpoint}")
        print(f"Conteúdo bruto da resposta (primeiros 500 caracteres): {json.dumps(data)[:500]}...")
        if isinstance(data, list):
            print(f"{len(data)} itens recebidos na lista.")
        elif isinstance(data, dict):
            print(f"Chaves do dicionário: {list(data.keys())}")
        return data
    except requests.exceptions.RequestException as e:
        print(f"Erro ao acessar {endpoint}: {e}")
        return {}
    except ValueError as e:
        print(f"Erro ao decodificar JSON de {endpoint}: {e}")
        return {}

def main() -> None:
    """Função principal do navegador textual."""
    # Primeiro, busca a lista de páginas do sistema via API
    print("Carregando estrutura de páginas do sistema...")
    pages_data = fetch_api_data("/sys_std_pages")
    if not pages_data:
        print("Não foi possível carregar a estrutura de páginas. Verifique a conexão com a API.")
        return  # Encerra o programa se não houver dados
    elif isinstance(pages_data, dict) and 'rows' in pages_data:
        print("Resposta da API é um dicionário com 'rows'. Tentando extrair a lista de linhas.")
        pages_data = pages_data.get('rows', [])
        print(f"{len(pages_data)} linhas extraídas de 'rows'.")
    elif not isinstance(pages_data, list):
        print("Não foi possível carregar a estrutura de páginas. O formato dos dados não é uma lista.")
        print(f"Tipo de dados recebido: {type(pages_data).__name__}")
        return  # Encerra o programa se o formato não for uma lista
    else:
        print(f"Estrutura de páginas carregada com sucesso: {len(pages_data)} páginas encontradas.")
    
    while True:
        site_pages = build_dynamic_menu(pages_data)
        display_menu(site_pages)
        choice = input("Escolha uma opção: ")
        if choice not in site_pages:
            print("Opção inválida. Tente novamente.")
            continue
        selected_page = site_pages[choice]
        if selected_page['endpoint'] is None:  # Opção de sair
            print("Saindo do navegador textual. Até logo!")
            break
        print(f"\nAcessando: {selected_page['name']} ({selected_page['endpoint']})")
        data = fetch_api_data(selected_page['endpoint'])
        display_json_data(data)
        additional_options = infer_options_from_data(data, selected_page)
        next_action = display_additional_options(additional_options)
        if next_action == "back":
            continue
        elif isinstance(next_action, dict) and next_action.get("action") == "detail":
            # Aqui, poderíamos implementar a navegação para detalhes de um item específico
            print(f"Navegação para detalhes do item {next_action.get('id')} ainda não implementada.")
            input("Pressione Enter para continuar...")

if __name__ == "__main__":
    main()