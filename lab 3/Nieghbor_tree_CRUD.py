import psycopg2
from prettytable import PrettyTable
from psycopg2 import sql

def is_valid_name(name):
    return bool(name.strip())

def is_valid_id(conn, node_id):
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) FROM animal_tree WHERE id = %s", (node_id,))
        return cur.fetchone()[0] > 0

def print_tree(conn, node_id, level=0):
    with conn.cursor() as cur:
        cur.execute("SELECT id, name FROM animal_tree WHERE id = %s", (node_id,))
        node = cur.fetchone()
        if node is None:
            print("Node not found.")
            return

        if level == 0:
            print('-------------------------------------------')
            print(f"{node[1]} (ID: {node[0]})")
        else:
            print("|   " * (level) + f"└── {node[1]} (ID: {node[0]})")

        cur.execute("SELECT id FROM animal_tree WHERE parent_id = %s ORDER BY id", (node_id,))
        children = cur.fetchall()

        for child in children:
            print_tree(conn, child[0], level + 1)

def reset_sequence(conn):
    with conn.cursor() as cur:
        try:
            cur.execute("""
                SELECT setval('animal_tree_id_seq', 
                (SELECT MAX(id) FROM animal_tree))
            """)
            conn.commit()
            print("Sequence reset successfully")
        except psycopg2.Error as e:
            conn.rollback()
            print("Error resetting sequence:", e)

def animal_exists(conn, name, parent_id):
    """Проверяет, существует ли уже животное с таким именем у данного родителя"""
    with conn.cursor() as cur:
        cur.execute("""
            SELECT COUNT(*) FROM animal_tree 
            WHERE name = %s AND parent_id = %s
        """, (name.strip(), parent_id))
        return cur.fetchone()[0] > 0

# 1. Добавление листа
def add_leaf(conn, name, parent_id):
    if not is_valid_name(name):
        print("Error: Name cannot be empty.")
        return None

    if not is_valid_id(conn, parent_id):
        print(f"Error: Parent ID {parent_id} does not exist.")
        return None

    if animal_exists(conn, name, parent_id):
        print(f"Error: Animal with name '{name}' already exists under parent ID {parent_id}.")
        return None

    with conn.cursor() as cur:
        try:
            # Вставка нового узла
            cur.execute(
                "INSERT INTO animal_tree (name, parent_id) VALUES (%s, %s) RETURNING id",
                (name.strip(), parent_id))
            new_id = cur.fetchone()[0]
            conn.commit()
            print(f"Added: {name} (ID: {new_id})")
            return new_id
        except psycopg2.Error as e:
            conn.rollback()
            print("Error adding leaf:", e)
            return None

# 2. Удаление листа
def delete_leaf(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return False

    with conn.cursor() as cur:
        try:
            # Проверяем, что узел не является корневым
            cur.execute("SELECT parent_id FROM animal_tree WHERE id = %s", (node_id,))
            parent_id = cur.fetchone()[0]
            if parent_id is None:
                print("Error: Cannot delete the root node.")
                return False
            
            # Проверяем, что узел действительно лист (нет потомков)
            cur.execute("SELECT COUNT(*) FROM animal_tree WHERE parent_id = %s", (node_id,))
            if cur.fetchone()[0] > 0:
                print("Error: Node is not a leaf (it has children).")
                return False
            
            cur.execute("DELETE FROM animal_tree WHERE id = %s", (node_id,))
            conn.commit()
            print("Leaf deleted successfully.")
            return True
        except psycopg2.Error as e:
            conn.rollback()
            print("Error deleting leaf:", e)
            return False

# 3. Удаление поддерева
def delete_subtree(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return False

    with conn.cursor() as cur:
        try:
            # Проверяем, не пытаемся ли удалить корень
            cur.execute("SELECT parent_id FROM animal_tree WHERE id = %s", (node_id,))
            if cur.fetchone()[0] is None:
                print("Error: Cannot delete the entire tree (root node).")
                return False
            
            cur.execute("""
                WITH RECURSIVE sub_tree AS (
                    SELECT id FROM animal_tree WHERE id = %s
                    UNION ALL
                    SELECT nt.id FROM animal_tree nt
                    JOIN sub_tree st ON nt.parent_id = st.id
                )
                DELETE FROM animal_tree WHERE id IN (SELECT id FROM sub_tree)
            """, (node_id,))
            
            conn.commit()
            print(f"Subtree with root ID {node_id} deleted successfully.")
            return True
        except psycopg2.Error as e:
            conn.rollback()
            print("Error deleting subtree:", e)
            return False

# 4. Удаление узла без поддерева
def delete_node_without_subtree(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return False

    with conn.cursor() as cur:
        try:
            # Получаем информацию об узле
            cur.execute("SELECT parent_id FROM animal_tree WHERE id = %s", (node_id,))
            result = cur.fetchone()
            
            if not result or result[0] is None:
                print("Cannot delete root node without subtree.")
                return False
                
            parent_id = result[0]
            
            # Проверяем, есть ли потомки
            cur.execute("SELECT COUNT(*) FROM animal_tree WHERE parent_id = %s", (node_id,))
            child_count = cur.fetchone()[0]
            
            if child_count == 0:
                # Если нет потомков, просто удаляем
                cur.execute("DELETE FROM animal_tree WHERE id = %s", (node_id,))
                conn.commit()
                print(f"Leaf node with ID {node_id} deleted.")
                return True
            
            # Переносим потомков к родителю
            cur.execute("""
                UPDATE animal_tree 
                SET parent_id = %s 
                WHERE parent_id = %s
            """, (parent_id, node_id))
            
            # Проверяем, нет ли конфликта имен после переноса
            cur.execute("""
                SELECT a.name, COUNT(*) 
                FROM animal_tree a
                JOIN animal_tree b ON a.parent_id = b.parent_id AND a.name = b.name
                WHERE a.parent_id = %s
                GROUP BY a.name
                HAVING COUNT(*) > 1
            """, (parent_id,))
            conflicts = cur.fetchall()
            
            if conflicts:
                conn.rollback()
                print("Error: Name conflicts detected after moving children:")
                for name, count in conflicts:
                    print(f"- Name '{name}' would have {count} duplicates under parent ID {parent_id}")
                return False
            
            # Удаляем узел
            cur.execute("DELETE FROM animal_tree WHERE id = %s", (node_id,))
            conn.commit()
            print(f"Node with ID {node_id} deleted without subtree.")
            return True
            
        except psycopg2.Error as e:
            conn.rollback()
            print("Error deleting node without subtree:", e)
            return False

# 5. Получение прямых потомков
def get_direct_descendants(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return

    with conn.cursor() as cur:
        cur.execute("""
            SELECT id, name, parent_id 
            FROM animal_tree 
            WHERE parent_id = %s 
            ORDER BY id
        """, (node_id,))
        
        results = cur.fetchall()
        if results:
            table = PrettyTable(["ID", "Name", "Parent ID"])
            for row in results:
                table.add_row(row)
            print(table)
        else:
            print("No direct descendants found.")

# 6. Получение прямого родителя
def get_direct_parent(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return

    with conn.cursor() as cur:
        # Сначала получаем parent_id текущего узла
        cur.execute("SELECT parent_id FROM animal_tree WHERE id = %s", (node_id,))
        result = cur.fetchone()
        
        if not result or result[0] is None:
            print("Node has no parent (it's the root).")
            return
            
        parent_id = result[0]
        
        # Теперь получаем информацию о родителе
        cur.execute("""
            SELECT id, name, parent_id 
            FROM animal_tree 
            WHERE id = %s
        """, (parent_id,))
        
        parent_info = cur.fetchone()
        if parent_info:
            table = PrettyTable(["ID", "Name", "Parent ID"])
            table.add_row(parent_info)
            print(table)
        else:
            print("Parent not found (inconsistent data).")

# 7. Получение всех потомков
def get_all_descendants(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return

    print(f"\nAll descendants of node {node_id}:")
    print_tree(conn, node_id)

# 8. Получение всех родителей
def get_all_parents(conn, node_id):
    if not is_valid_id(conn, node_id):
        print(f"Error: Node ID {node_id} does not exist.")
        return

    with conn.cursor() as cur:
        cur.execute("""
            WITH RECURSIVE parent_tree AS (
                SELECT id, name, parent_id, 1 as level
                FROM animal_tree
                WHERE id = %s
                
                UNION ALL
                
                SELECT p.id, p.name, p.parent_id, pt.level + 1
                FROM animal_tree p
                JOIN parent_tree pt ON p.id = pt.parent_id
            )
            SELECT id, name, level
            FROM parent_tree
            WHERE id != %s
            ORDER BY level DESC
        """, (node_id, node_id))
        
        parents = cur.fetchall()
        
        if not parents:
            print("Node has no parents (it's the root).")
            return
            
        print("\nPath to root:")
        table = PrettyTable(["Level", "ID", "Name"])
        for id, name, level in parents:
            table.add_row([level, id, name])
        print(table)

def main():
    try:
        conn = psycopg2.connect(
            dbname="animal_tree",
            user="zhambal",
            password="TZkZWYs9rLNT7OC1iTSahVnQFhACn3Y2",
            host="dpg-d11rlt95pdvs73c2cnmg-a.oregon-postgres.render.com"
        )
        
        reset_sequence(conn)
        print("Current tree structure:")
        print_tree(conn, 1)

        while True:
            print("\nMenu:")
            print("1. Add Leaf")
            print("2. Delete Leaf")
            print("3. Delete Subtree")
            print("4. Delete Node without Subtree")
            print("5. Get Direct Descendants")
            print("6. Get Direct Parent")
            print("7. Get All Descendants")
            print("8. Get All Parents")
            print("9. Print full tree")
            print("10. Exit")
            
            choice = input("Enter your choice (1-10): ").strip()
            
            if choice == '1':
                name = input("Enter name of the new leaf: ").strip()
                parent_id = input("Enter parent ID: ").strip()
                try:
                    add_leaf(conn, name, int(parent_id))
                except ValueError:
                    print("Invalid parent ID. Please enter a number.")
            
            elif choice == '2':
                node_id = input("Enter leaf ID to delete: ").strip()
                try:
                    delete_leaf(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '3':
                node_id = input("Enter node ID to delete subtree: ").strip()
                try:
                    delete_subtree(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '4':
                node_id = input("Enter node ID to delete without subtree: ").strip()
                try:
                    delete_node_without_subtree(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '5':
                node_id = input("Enter node ID to get direct descendants: ").strip()
                try:
                    get_direct_descendants(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '6':
                node_id = input("Enter node ID to get direct parent: ").strip()
                try:
                    get_direct_parent(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '7':
                node_id = input("Enter node ID to get all descendants: ").strip()
                try:
                    get_all_descendants(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
            
            elif choice == '8':
                node_id = input("Enter node ID to get all parents: ").strip()
                try:
                    get_all_parents(conn, int(node_id))
                except ValueError:
                    print("Invalid node ID. Please enter a number.")
                
            elif choice == '9':  # Добавьте новый пункт меню
                print("\nFull tree structure:")
                with conn.cursor() as cur:
                    cur.execute("SELECT id FROM animal_tree WHERE parent_id IS NULL")
                    root_id = cur.fetchone()[0]
                print_tree(conn, root_id)
            
            elif choice == '10':
                print("Exiting program.")
                break
            
            else:
                print("Invalid choice. Please enter a number from 1 to 10.")
    
    except psycopg2.Error as e:
        print(f"Database error: {e}")
    except Exception as e:
        print(f"Error: {e}")
    finally:
        if 'conn' in locals() and conn:
            conn.close()

if __name__ == "__main__":
    main()