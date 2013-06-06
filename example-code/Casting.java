public class Casting {
    public static void main(String[] args) {
        int[] e = {1,2,3};
        Object f = e;
        e = null; // instance is still int[]
        System.out.println(((int[])f)[0]); // 1
     }
}
        