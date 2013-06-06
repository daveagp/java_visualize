public class LinkedList {
    static class Node {
        Node next;
        int val;
    }

    public static void main(String[] args) {
        Node list = null;
        Node first = null;
        for (int i=0; i<5; i++) {
            Node tmp = new Node();
            tmp.next = list;
            tmp.val = i;
            list = tmp;
            if (i==0) first = tmp;
        }
        first.next = list;
        // ourobouros!
    }
}