import matplotlib.pyplot as plt
import networkx as nx
from collections import deque

class GraphStructure:
    def __init__(self):
        self.graph = {}

    def add_edge(self, node, neighbor):
        if node not in self.graph:
            self.graph[node] = []
        if neighbor not in self.graph:
            self.graph[neighbor] = []
        self.graph[node].append(neighbor)

    def show_graph(self):
        for node in self.graph:
            print(f"{node} -> {self.graph[node]}")

    def plot_graph(self, highlight_nodes=None):
        import networkx as nx
        G = nx.DiGraph(self.graph)
        pos = nx.spring_layout(G)
        node_colors = []
        for node in G.nodes():
            if highlight_nodes and node in highlight_nodes:
                node_colors.append('orange')
            else:
                node_colors.append('skyblue')

        nx.draw(
            G,
            pos,
            with_labels=True,
            node_color=node_colors,
            node_size=1500,
            font_weight='bold',
            font_color='black'
        )
        plt.title("Graph Structure")
        plt.show()

    # BFS Method
    def bfs(self, start):
        visited = set()
        queue = deque([start])
        order = []

        while queue:
            node = queue.popleft()
            if node not in visited:
                visited.add(node)
                order.append(node)
                queue.extend(neighbor for neighbor in self.graph[node] if neighbor not in visited)

        print("BFS Traversal:", order)
        self.plot_graph(order)

    # DFS Method
    def dfs(self, start):
        visited = set()
        order = []

        def dfs_recursive(node):
            if node not in visited:
                visited.add(node)
                order.append(node)
                for neighbor in self.graph[node]:
                    dfs_recursive(neighbor)

        dfs_recursive(start)
        print("DFS Traversal:", order)
        self.plot_graph(order)


if __name__ == "__main__":
    g = GraphStructure()
    g.add_edge('A', 'B')
    g.add_edge('A', 'C')
    g.add_edge('B', 'D')
    g.add_edge('C', 'E')
    g.add_edge('D', 'F')

    print("Graph Structure:")
    g.show_graph()

    g.bfs('A')
    g.dfs('A')
