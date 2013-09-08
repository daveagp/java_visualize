public class Static {
    public static class Inner { // needs to be static for x to be static
        static int x;
    }
    static int y;
    public static void main(String[] args) {
        y = 1;
        Inner.x = 2;
    }
}