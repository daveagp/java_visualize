// Princeton COS 126 Written Exam 2, Fall 2010, Question 3

public class Forest {
    private Node[] links;
    private class Node {
        private Node next;
    }
    public Forest(int N) {
        links = new Node[N];
        for (int i = 0; i < N; i++)
            links[i] = new Node();
    }
    private Node root(int i) {
        Node x = links[i];
        while (x.next != null) x = x.next;
        return x;
    }
    public void merge(int i, int j) {
        root(i).next = root(j);
    }
    public boolean merged(int i, int j) {
        return root(i) == root(j);
    }
    public static void main(String[] args) {
        Forest t = new Forest(6);
        t.merge(0, 1);
        t.merge(2, 1); 
        t.merge(4, 5);
        
        t = new Forest(8);
        t.merge(0, 3);
        t.merge(1, 2);
        t.merge(1, 4);
        t.merge(5, 6);
        t.merge(3, 4);
        t.merge(7, 5);
        
        t.merged(0, 3);
        t.merged(0, 7);
        t.merged(1, 3);
        t.merged(4, 5);
    }
}